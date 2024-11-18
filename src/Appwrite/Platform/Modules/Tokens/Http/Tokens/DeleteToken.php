<?php

namespace Appwrite\Platform\Modules\Tokens\Http\Tokens;

use Appwrite\Event\Event;
use Appwrite\Extend\Exception;
use Appwrite\Utopia\Response;
use Utopia\Database\Database;
use Utopia\Database\Validator\UID;
use Utopia\Platform\Action;
use Utopia\Platform\Scope\HTTP;

class DeleteToken extends Action
{
    use HTTP;

    public static function getName()
    {
        return 'deleteToken';
    }

    public function __construct()
    {
        $this->setHttpMethod(Action::HTTP_REQUEST_METHOD_DELETE)
        ->setHttpPath('/v1/tokens/:tokenId')
        ->desc('Delete token')
        ->groups(['api', 'tokens'])
        ->label('scope', 'tokens.write')
        ->label('event', 'tokens.[tokenId].delete')
        ->label('audits.event', 'tokens.delete')
        ->label('audits.resource', 'token/{request.tokenId}')
        ->label('usage.metric', 'tokens.{scope}.requests.delete')
        ->label('usage.params', ['tokenId:{request.tokenId}'])
        ->label('abuse-key', 'ip:{ip},method:{method},url:{url},userId:{userId}')
        ->label('abuse-limit', APP_LIMIT_WRITE_RATE_DEFAULT)
        ->label('abuse-time', APP_LIMIT_WRITE_RATE_PERIOD_DEFAULT)
        ->label('sdk.auth', [APP_AUTH_TYPE_SESSION, APP_AUTH_TYPE_KEY, APP_AUTH_TYPE_JWT])
        ->label('sdk.namespace', 'tokens')
        ->label('sdk.method', 'delete')
        ->label('sdk.description', '/docs/references/tokens/delete.md')
        ->label('sdk.response.code', Response::STATUS_CODE_NOCONTENT)
        ->label('sdk.response.model', Response::MODEL_NONE)
        ->param('tokenId', '', new UID(), 'Token ID.')
        ->inject('response')
        ->inject('dbForProject')
        ->inject('queueForEvents')
        ->callback(fn ($tokenId, $response, $dbForProject, $queueForEvents) => $this->action($tokenId, $response, $dbForProject, $queueForEvents));
    }

    public function action(string $tokenId, Response $response, Database $dbForProject, Event $queueForEvents)
    {
        $token = $dbForProject->getDocument('resourceTokens', $tokenId);
        if ($token->isEmpty()) {
            throw new Exception(Exception::TOKEN_NOT_FOUND);
        }

        $dbForProject->deleteDocument('resourceTokens', $tokenId);

        $queueForEvents
            ->setParam('tokenId', $token->getId())
            ->setPayload($response->output($token, Response::MODEL_RESOURCE_TOKEN))
        ;

        $response->noContent();
    }
}
