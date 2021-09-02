<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\DI;

use Doctrine\DBAL\Driver\Connection;
use FreezyBee\NetteCachingPsr6\Cache;
use FreezyBee\PrependRoute\DI\IPrependRouteProvider;
use FreezyBee\PrependRoute\DI\PrependRouteExtension;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\MissingServiceException;
use Nette\InvalidArgumentException;
use Nette\NotImplementedException;
use PDO as XPDO;
use RuntimeException;
use XcoreCMS\InlineEditing\Model\Entity\EntityPersister;
use XcoreCMS\InlineEditing\Model\Simple\ContentProvider;
use XcoreCMS\InlineEditing\Model\Simple\PersistenceLayer\Dbal;
use XcoreCMS\InlineEditing\Model\Simple\PersistenceLayer\Dibi;
use XcoreCMS\InlineEditing\Model\Simple\PersistenceLayer\NetteDatabase;
use XcoreCMS\InlineEditing\Model\Simple\PersistenceLayer\Pdo;
use XcoreCMS\InlineEditingNette\Handler\Route;
use XcoreCMS\InlineEditingNette\Latte\Macros;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;
use XcoreCMS\InlineEditingNette\Security\SimpleUserRoleCheckerService;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
class InlineEditingExtension extends CompilerExtension implements IPrependRouteProvider
{
    /**
     * @var array<string, mixed>
     */
    private $defaults = [
        'fallback' => false,
        'tableName' => 'inline_content',
        'persistenceLayer' => null,
        'url' => '/inline-editing',
        'assetsDir' => 'inline',
        'allowedRoles' => null,
        'entityMode' => false,
        'translator' => null,
        'install' => [
            'assets' => true,
            'database' => true
        ]
    ];

    /** @var mixed[]|string|null */
    private $persistenceConfig;

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
            ->setFactory(Route::class, [$config['url']])
            ->setAutowired(false);

        // cache
        $cache = $builder
            ->addDefinition($this->prefix('cache'))
            ->setFactory(Cache::class, ['@cache.storage', 'inlineEditing']);

        // persistence layer
        if ($this->config['persistenceLayer'] === null) {
            // detect
            if ($builder->hasDefinition('doctrine.default.connection')) {
                $persistenceDef = '@doctrine.default.connection';
                $persistenceClass = Dbal::class;
            } elseif ($builder->hasDefinition('database.default.connection')) {
                $persistenceDef = '@database.default.connection';
                $persistenceClass = NetteDatabase::class;
            } elseif ($builder->hasDefinition('dibi.connection')) {
                $persistenceDef = '@dibi.connection';
                $persistenceClass = Dibi::class;
            } else {
                throw new InvalidArgumentException(__CLASS__ . ' can not detect persistence layer');
            }
            $this->persistenceConfig = $persistenceClass;
        } else {
            $persistenceDef = $this->config['persistenceLayer'];
            if ($persistenceDef instanceof Statement && $persistenceDef->entity === 'PDO') {
                $persistenceClass = Pdo::class;
                $this->persistenceConfig = $persistenceDef->arguments;
            } else {
                $this->persistenceConfig = $persistenceClass = Dbal::class;
            }
        }

        $persistenceLayer = $builder
            ->addDefinition($this->prefix('persistenceLayer'))
            ->setFactory($persistenceClass, [$this->config['tableName'], $persistenceDef]);

        // content provider
        $builder
            ->addDefinition($this->prefix('contentProvider'))
            ->setFactory(ContentProvider::class, [['fallback' => $config['fallback']], $cache, $persistenceLayer]);

        // inline permission checker
        $builder
            ->addDefinition($this->prefix('permissionChecker'))
            ->setType(InlinePermissionChecker::class);

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

        /** @var mixed $config */
        $config = $this->getConfig();
        $translator = $config['translator'];

        /** @var FactoryDefinition $latteFactoryDef */
        $latteFactoryDef = $builder->getDefinitionByType(LatteFactory::class);
        $latteFactory = $latteFactoryDef->getResultDefinition();

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
        if ($config['install']['database'] === true) {
            $this->initDatabaseTable();
        }
    }

    /**
     * Create symlink to client side resources
     */
    protected function linkResourceDirectory(): void
    {
        $params = $this->getContainerBuilder()->parameters;

        /** @var mixed $config */
        $config = $this->getConfig();

        $originDir = __DIR__ . '/../../../inline-editing/client-side/dist';
        $targetDir = $params['wwwDir'] . '/' . $config['assetsDir'];

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

        /** @var mixed $config */
        $config = $this->getConfig();
        $tableName = $config['tableName'];

        switch (true) {
            case $this->persistenceConfig === Dbal::class:
                /** @var FactoryDefinition $dbal */
                $dbal = $builder->getDefinitionByType(Connection::class);
                $factory = $dbal->getResultDefinition()->getFactory();
                $options = $factory->arguments[0];

                $driver = strpos($options['driver'], 'mysql') !== false ? 'mysql' : $options['driver'];
                $dsn = $driver . ':host=' . $options['host'] . ';dbname=' . $options['dbname'];
                $username = $options['user'];
                $password = $options['password'];
                break;
            case is_array($this->persistenceConfig):
                $dsn = $this->persistenceConfig[0];
                $username = $this->persistenceConfig[1];
                $password = $this->persistenceConfig[2] ?? null;
                break;
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

        $pdo = new XPDO($dsn, $username, $password);
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
