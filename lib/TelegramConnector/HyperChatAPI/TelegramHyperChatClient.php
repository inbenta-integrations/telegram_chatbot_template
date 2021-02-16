<?php

namespace Inbenta\TelegramConnector\HyperChatAPI;

use Inbenta\ChatbotConnector\HyperChatAPI\HyperChatClient;
use Inbenta\TelegramConnector\ExternalAPI\TelegramAPIClient;

class TelegramHyperChatClient extends HyperChatClient
{

    //Instances an external client
    protected function instanceExternalClient($externalId, $appConf)
    {
        $externalId = TelegramAPIClient::getIdFromExternalId($externalId);
        if (is_null($externalId)) {
            return null;
        }
        $externalClient = new TelegramAPIClient($appConf->get('tg.bot_token'));
        $externalClient->setSenderFromId($externalId);
        return $externalClient;
    }

    public static function buildExternalIdFromRequest($config)
    {
        $request = json_decode(file_get_contents('php://input'), true);

        $externalId = null;
        if (isset($request['trigger'])) {
            //Obtain user external id from the chat event
            $externalId = self::getExternalIdFromEvent($config, $request);
        }
        return $externalId;
    }
}
