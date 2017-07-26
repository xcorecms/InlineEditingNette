<?php
declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Handler;

use Closure;
use Nette\Application\IRouter;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\Url;
use Nette\SmartObject;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use XcoreCMS\InlineEditing\Model\Entity\EntityPersister;
use XcoreCMS\InlineEditing\Model\Entity\HtmlEntityElement\Element;
use XcoreCMS\InlineEditing\Model\Simple\ContentProvider;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 * @method void onInvoke()
 */
class Route implements IRouter
{
    use SmartObject;

    /** @var Closure[] */
    public $onInvoke = [];

    /** @var string */
    private $mask;

    /** @var ContentProvider */
    private $contentProvider;

    /** @var InlinePermissionChecker */
    private $permissionChecker;

    /** @var IResponse */
    private $response;

    /** @var EntityPersister|null */
    private $entityPersister;

    /**
     * @param string $mask
     * @param ContentProvider $contentProvider
     * @param InlinePermissionChecker $permissionChecker
     * @param IResponse $response
     * @param EntityPersister|null $entityPersister
     */
    public function __construct(
        string $mask,
        ContentProvider $contentProvider,
        InlinePermissionChecker $permissionChecker,
        IResponse $response,
        ?EntityPersister $entityPersister = null
    ) {
        $this->mask = $mask;
        $this->contentProvider = $contentProvider;
        $this->permissionChecker = $permissionChecker;
        $this->response = $response;
        $this->entityPersister = $entityPersister;
    }

    /**
     * {@inheritdoc}
     */
    public function match(IRequest $httpRequest): ?Request
    {
        return ($httpRequest->getUrl()->getPath() === $this->mask && $httpRequest->getMethod() === IRequest::POST) ?
            new Request(
                'Nette:Micro',
                IRequest::POST,
                ['callback' => $this],
                [],
                [],
                [Request::SECURED => $httpRequest->isSecured()]
            ) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function constructUrl(Request $appRequest, Url $refUrl): ?string
    {
        return null;
    }

    /**
     *
     */
    public function __invoke()
    {
        $this->onInvoke();

        try {
            /** @var array $data */
            $data = Json::decode(file_get_contents('php://input'), JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $exception) {
            $this->response->setCode(IResponse::S500_INTERNAL_SERVER_ERROR);
            return new JsonResponse([]);
        }

        $payload = [];

        foreach ($data as $elementId => $item) {
            $type = $item['type'] ?? null;
            if ($type === 'simple') {
                $payload[$elementId] = $this->processSimple($item);
            } elseif ($type === 'entity' || $type === 'entity-specific') {
                $this->processEntity($item);
            }
        }

        // only if entityPersister is loaded
        if ($this->entityPersister !== null) {
            $container = $this->entityPersister->flush();
            $payload = array_merge($payload, $container->generateResponse());
            if ($container->isValid() === false) {
                $this->response->setCode(IResponse::S400_BAD_REQUEST);
            }
        }

        return new JsonResponse($payload);
    }

    /**
     * @param array $item
     * @return array
     */
    protected function processSimple(array $item): array
    {
        $namespace = $item['namespace'] ?? '';
        $locale = $item['locale'] ?? '';
        $name = $item['name'] ?? '';

        if (!$this->permissionChecker->isItemEditationAllowed($namespace, $locale, $name)) {
            $this->response->setCode(IResponse::S403_FORBIDDEN);
            return ['status' => 2, 'message' => 'Forbidden'];
        }

        $this->contentProvider->saveContent(
            $namespace,
            $locale,
            $name,
            $item['content'] ?? ''
        );

        return ['status' => 0];
    }

    /**
     * @param array $item
     */
    protected function processEntity(array $item)
    {
        if ($this->entityPersister === null) {
            throw new \RuntimeException(
                'Please set entityMode: true in config file for allowing ' . EntityPersister::class
            );
        }

        if (!isset($item['entity'], $item['id'], $item['property'], $item['content'])) {
            return;
        }

        $className = $item['entity'] ?? '';
        $id = $item['id'] ?? '';
        $property = $item['property'] ?? '';
        $value = $item['content'] ?? '';

        $this->entityPersister->update(new Element($className, $id, $property, $value), function ($entity) {
            return $this->permissionChecker->isEntityEditationAllowed($entity) ? false : 'Forbidden';
        });
    }
}
