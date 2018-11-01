<?php
declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\DI;

use FreezyBee\NetteCachingPsr6\Cache;
use FreezyBee\PrependRoute\DI\IPrependRouteProvider;
use FreezyBee\PrependRoute\DI\PrependRouteExtension;
use Nette\Bridges\ApplicationLatte\ILatteFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\MissingServiceException;
use Nette\InvalidArgumentException;
use Nette\NotImplementedException;
use PDO;
use RuntimeException;
use XcoreCMS\InlineEditing\Model\Entity\EntityPersister;
use XcoreCMS\InlineEditing\Model\Simple\ContentProvider;
use XcoreCMS\InlineEditing\Model\Simple\PersistenceLayer\Dbal;
use XcoreCMS\InlineEditing\Model\Simple\PersistenceLayer\Dibi;
use XcoreCMS\InlineEditing\Model\Simple\PersistenceLayer\NetteDatabase;
use XcoreCMS\InlineEditingNette\Handler\Route;
use XcoreCMS\InlineEditingNette\Latte\Macros;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;
use Symfony\Component\Translation\TranslatorInterface;
use XcoreCMS\InlineEditingNette\Security\SimpleUserRoleCheckerService;
use Kdyby\Doctrine\Connection;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
class InlineEditingExtension extends CompilerExtension implements IPrependRouteProvider
{
    /**
     * @var array
     */
    private $defaults = [
        'fallback' => false,
        'tableName' => 'inline_content',
        'persistenceLayer' => null,
        'url' => '/inline-editing',
        'assetsDir' => 'inline',
        'allowedRoles' => null,
        'entityMode' => false,
        'install' => [
            'assets' => true,
            'database' => true
        ]
    ];

    /** @var string|null */
    private $persistenceClass;

    /**
     *
     */
    public function loadConfiguration(): void
    {
        $config = $this->config = $this->validateConfig($this->defaults);
        $builder = $this->getContainerBuilder();

        // router
        if (!$this->compiler->getExtensions(PrependRouteExtension::class)) {
            throw new MissingServiceException('You must register PrependRouteExtension');
        }

        $routerDef = $builder
            ->addDefinition($this->prefix('router'))
            ->setClass(Route::class, [$config['url']])
            ->setAutowired(false);

        // cache
        $cache = $builder
            ->addDefinition($this->prefix('cache'))
            ->setClass(Cache::class, ['@cache.storage', 'inlineEditing']);

        // persistence layer
        if ($this->config['persistenceLayer'] === null) {
            // detect
            if ($builder->hasDefinition('doctrine.default.connection')) {
                $persistenceDef = '@doctrine.default.connection';
                $persistenceClass = Dbal::class;
            } elseif ($builder->hasDefinition('nette.database.connection')) {
                $persistenceDef = '@nette.database.connection';
                $persistenceClass = NetteDatabase::class;
            } elseif ($builder->hasDefinition('dibi.connection')) {
                $persistenceDef = '@dibi.connection';
                $persistenceClass = Dibi::class;
            } else {
                throw new InvalidArgumentException(__CLASS__ . ' can not detect persistence layer');
            }
        } else {
            $persistenceDef = $this->config['persistenceLayer'];
            $persistenceClass = Dbal::class;
        }

        $this->persistenceClass = $persistenceClass;

        $persistenceLayer = $builder
            ->addDefinition($this->prefix('persistenceLayer'))
            ->setClass($persistenceClass, [$this->config['tableName'], $persistenceDef]);

        // content provider
        $builder
            ->addDefinition($this->prefix('contentProvider'))
            ->setClass(ContentProvider::class, [['fallback' => $config['fallback']], $cache, $persistenceLayer]);

        // inline permission checker
        $builder
            ->addDefinition($this->prefix('permissionChecker'))
            ->setClass(InlinePermissionChecker::class);

        // simple user role checker
        if (is_array($config['allowedRoles'])) {
            $builder
                ->addDefinition($this->prefix('simpleChecker'))
                ->setFactory(SimpleUserRoleCheckerService::class, [$config['allowedRoles']])
                ->addTag('run');
        }

        // entityMode
        if ($config['entityMode'] === true) {
            // entity persister
            $entityPersisterDef = $builder
                ->addDefinition($this->prefix('entityPersister'))
                ->setType(EntityPersister::class);

            if ($routerDef->getFactory() === null) {
                throw new RuntimeException('Router factory def is null');
            }

            $routerDef->getFactory()->arguments['entityPersister'] = $entityPersisterDef;
        }

        if ($config['install']['assets'] === true) {
            $this->linkResourceDirectory();
        }
    }

    /**
     * @throws \Nette\InvalidArgumentException
     */
    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();

        // check translator exists
        $translator = null;
        foreach ($builder->getDefinitions() as $name => $def) {
            $implements = $def->getType() !== null ? class_implements($def->getType()) : [];
            if (isset($implements[TranslatorInterface::class])) {
                $translator = "@$name";
                break;
            }
        }

        $latteFactory = $builder->getDefinitionByType(ILatteFactory::class);

        // macros
        $latteFactory->addSetup(
            '?->onCompile[] = function($engine) {' .
            Macros::class . '::install($engine->getCompiler(), ' . ($translator !== null ? 'true' : 'false') . ');}',
            ['@self']
        );

        // filters
        $latteFactory->addSetup(
            '?->addFilter(\'inlineEditingContent\', function(\?string $namespace, \?string $locale, string $name) {
                return ?->getContent((string) $namespace, (string) $locale, $name);
            })',
            ['@self', $this->prefix('@contentProvider')]
        );

        // providers
        $latteFactory->addSetup(
            '?->addProvider(\'inlinePermissionChecker\', ?)',
            ['@self', $this->prefix('@permissionChecker')]
        );

        if ($translator) {
            $latteFactory->addSetup('?->addProvider(\'inlineTranslatorProvider\', ?)', ['@self', $translator]);
        }

        // init db
        if ($this->getConfig()['install']['database'] === true) {
            $this->initDatabaseTable();
        }
    }

    /**
     * Create symlink to client side resources
     */
    protected function linkResourceDirectory(): void
    {
        $params = $this->getContainerBuilder()->parameters;

        $originDir = __DIR__ . '/../../../inline-editing/client-side/dist';
        $targetDir = $params['wwwDir'] . '/' . $this->getConfig()['assetsDir'];

        if ('\\' === DIRECTORY_SEPARATOR) {
            $originDir = str_replace('/', '\\', $originDir);
            $targetDir = str_replace('/', '\\', $targetDir);
        }

        if (!file_exists($targetDir)) {
            symlink($originDir, $targetDir);
        }
    }

    /**
     * Create table if not exists
     */
    protected function initDatabaseTable(): void
    {
        $builder = $this->getContainerBuilder();
        $tableName = $this->config['tableName'];

        switch (true) {
            case $this->persistenceClass === Dbal::class:
                $factory = $builder->getDefinitionByType(Connection::class)->getFactory();
                if ($factory === null) {
                    throw new MissingServiceException('Can\'t find definition of ' . Connection::class);
                }
                $options = $factory->arguments[0];

                $driver = strpos($options['driver'], 'mysql') !== false ? 'mysql' : $options['driver'];
                $dsn = $driver . ':host=' . $options['host'] . ';dbname=' . $options['dbname'];
                $username = $options['user'];
                $password = $options['password'];
                break;
            // TODO ndb + dibi
            default:
                throw new NotImplementedException();
        }

        if (strpos($dsn, 'mysql') === 0) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `namespace` varchar(255) NOT NULL,
            `locale` varchar(2) NOT NULL,
            `name` varchar(255) NOT NULL,
            `content` text NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique` (`namespace`,`locale`,`name`),
            KEY `index` (`namespace`,`locale`,`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        } elseif (strpos($dsn, 'pgsql') === 0 || strpos($dsn, 'postgre') === 0) {
            $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id integer NOT NULL,
                namespace character varying NOT NULL,
                locale character(2) NOT NULL,
                name character varying NOT NULL,
                content text NOT NULL
            );
        
            CREATE SEQUENCE {$tableName}_id_seq
                START WITH 1
                INCREMENT BY 1
                NO MINVALUE
                NO MAXVALUE
                CACHE 1;
        
            ALTER TABLE ONLY $tableName ALTER COLUMN id SET DEFAULT nextval('{$tableName}_id_seq'::regclass);
            SELECT pg_catalog.setval('{$tableName}_id_seq', 1, false);
            ALTER TABLE ONLY $tableName ADD CONSTRAINT {$tableName}_id PRIMARY KEY (id);
            ALTER TABLE ONLY $tableName ADD CONSTRAINT {$tableName}_unique UNIQUE (namespace, locale, name);
            CREATE INDEX {$tableName}_index ON $tableName USING btree (namespace, locale, name);";
        } else {
            throw new RuntimeException('Invalid pdo driver. Supported: mysql|pgsql|postgre');
        }

        $pdo = new PDO($dsn, $username, $password);
        $pdo->exec($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getPrependRoutes(): array
    {
        return [$this->prefix('router')];
    }
}
