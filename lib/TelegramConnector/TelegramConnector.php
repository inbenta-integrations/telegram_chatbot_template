<?php

namespace Inbenta\TelegramConnector;

use Exception;
use Inbenta\ChatbotConnector\ChatbotConnector;
use Inbenta\ChatbotConnector\Utils\SessionManager;
use Inbenta\ChatbotConnector\ChatbotAPI\ChatbotAPIClient;
use Inbenta\TelegramConnector\ExternalAPI\TelegramAPIClient;
use Inbenta\TelegramConnector\ExternalAPI\SetWebhook;
use Inbenta\TelegramConnector\ExternalDigester\TelegramDigester;
use Inbenta\TelegramConnector\HyperChatAPI\TelegramHyperChatClient;


class TelegramConnector extends ChatbotConnector
{

    public function __construct($appPath)
    {
        // Initialize and configure specific components for Telegram
        try {
            parent::__construct($appPath);

            // Initialize base components
            $request = file_get_contents('php://input');

            if (isset($_REQUEST["subscribe"]) && isset($_REQUEST["token"])) {
                SetWebhook::subscribe($appPath, (int) $_REQUEST["subscribe"], $_REQUEST["token"]);
            }

            $conversationConf = array('configuration' => $this->conf->get('conversation.default'), 'userType' => $this->conf->get('conversation.user_type'), 'environment' => $this->environment);
            $this->session      = new SessionManager($this->getExternalIdFromRequest());
            $this->botClient    = new ChatbotAPIClient($this->conf->get('api.key'), $this->conf->get('api.secret'), $this->session, $conversationConf);

            $this->conf->set('tg.bot_token', $this->conf->get('telegram.token'));

            // Try to get the translations from ExtraInfo and update the language manager
            $this->getTranslationsFromExtraInfo('telegram', 'translations');

            // Initialize Hyperchat events handler
            if ($this->conf->get('chat.chat.enabled') && ($this->session->get('chatOnGoing', false) || isset($_SERVER['HTTP_X_HOOK_SECRET']))) {
                $chatEventsHandler = new TelegramHyperChatClient($this->conf->get('chat.chat'), $this->lang, $this->session, $this->conf, $this->externalClient);
                $chatEventsHandler->handleChatEvent();
            }

            // Instance application components
            $externalClient = new TelegramAPIClient($this->conf->get('tg.bot_token'), $request);                                                // Instance Telegram client
            $chatClient     = new TelegramHyperChatClient($this->conf->get('chat.chat'), $this->lang, $this->session, $this->conf, $externalClient);  // Instance HyperchatClient for Telegram
            $externalDigester = new TelegramDigester($this->lang, $this->conf->get('conversation.digester'), $this->session, $externalClient);                         // Instance Telegram digester
            $this->initComponents($externalClient, $chatClient, $externalDigester);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
            die();
        }
    }

    /**
     *  Return external id from request (from Hyperchat chat or Telegram message)
     */
    protected function getExternalIdFromRequest()
    {
        // Try to get user_id from a Telegram message request
        $externalId = TelegramAPIClient::buildExternalIdFromRequest();
        if (is_null($externalId)) {
            // Try to get user_id from a Hyperchat event request
            $externalId = TelegramHyperChatClient::buildExternalIdFromRequest($this->conf->get('chat.chat'));
        }
        if (empty($externalId)) {
            $api_key = $this->conf->get('api.key');
            if (isset($_SERVER['HTTP_X_HOOK_SECRET'])) {
                // Create a temporary session_id from a HyperChat webhook linking request
                $externalId = "hc-challenge-" . preg_replace("/[^A-Za-z0-9 ]/", '', $api_key);
            } else {
                throw new Exception("Invalid request");
                die();
            }
        }
        return $externalId;
    }

    /**
     *  Send messages to the external service. Messages should be formatted as a ChatbotAPI response
     */
    protected function sendMessagesToExternal($messages)
    {
        // Digest the bot response into the external service format
        $digestedBotResponse = $this->digester->digestFromApi($messages,  $this->session->get('lastUserQuestion'));
        foreach ($digestedBotResponse as $message) {
            if (isset($message['type']) && $message['type'] !== '') {
                $type = $message['type'];
                unset($message['type']);
                $this->externalClient->sendMessage($message, $type);
            } else {
                $this->externalClient->sendMessage($message);
            }
        }
    }

    /**
     * Overwritten
     */
    protected function checkContentRatingsComment($message)
    {
        // If is a rating message
        $ratingOptions = $this->session->get('ratingOptionsMap', false);
        $this->session->set('ratingOptionsMap', false);
        if ($ratingOptions !== false && isset($message['message']) && isset($ratingOptions[$message['message']])) {
            $message = $ratingOptions[$message['message']];

            // Update negativeRatingCount to escalate if necessary
            $negativeRatingCount = $this->session->get('negativeRatingCount');
            if (isset($message['isNegativeRating']) && $message['isNegativeRating']) {
                $negativeRatingCount += 1;
            } else {
                $negativeRatingCount = 0;
            }
            $this->session->set('negativeRatingCount', $negativeRatingCount);

            // Handle a rating that should ask for a comment
            if ($message['askRatingComment']) {
                // Save the rating data to session to use later, when user sends his comment
                $this->session->set('askingRatingComment', $message['ratingData']);
            }

            // Return rating data to log the rating
            return $message['ratingData'];
        } elseif ($this->session->has('askingRatingComment') && $this->session->get('askingRatingComment') != false) {
            // Send the rating with comment
            $ratingData = $this->session->get('askingRatingComment');
            $ratingData['data']['comment'] = $message['message'];

            // Forget we're asking for a rating comment
            $this->session->set('askingRatingComment', false);
            return $ratingData;
        }
        return $message;
    }
}
