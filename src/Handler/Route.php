<?php
declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Handler;

use Nette\Application\Routers\Route as NetteRoute;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use XcoreCMS\InlineEditing\Model\ContentProvider;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
class Route extends NetteRoute
{
    /**
     * @param string $mask
     * @param ContentProvider $contentProvider
     * @param InlinePermissionChecker $permissionChecker
     * @param IResponse $response
     */
    public function __construct(
        string $mask,
        ContentProvider $contentProvider,
        InlinePermissionChecker $permissionChecker,
        IResponse $response
    ) {
        parent::__construct($mask, function () use ($contentProvider, $permissionChecker, $response) {
            try {
                /** @var \stdClass[] $data */
                $data = Json::decode(file_get_contents('php://input'));
            } catch (JsonException $exception) {
                $response->setCode(IResponse::S500_INTERNAL_SERVER_ERROR);
                return;
            }

            foreach ($data as $item) {
                $namespace = $item->namespace ?? '';
                $locale = $item->locale ?? '';
                $name = $item->name ?? '';

                if (!$permissionChecker->isItemEditationAllowed($namespace, $locale, $name)) {
                    $response->setCode(IResponse::S403_FORBIDDEN);
                    continue;
                }

                $contentProvider->saveContent(
                    $namespace,
                    $locale,
                    $name,
                    $item->content ?? ''
                );
            }
        });
    }
}
