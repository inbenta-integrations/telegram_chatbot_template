<?php

namespace Inbenta\TelegramConnector\ExternalAPI;

use Exception;
use GuzzleHttp\Client as Guzzle;
use Psr\Http\Message\ResponseInterface;

class TelegramAPIClient
{

    /**
     * Telegram API URL.
     *
     * @var string
     */
    protected $api_url = 'https://api.telegram.org/bot{TOKEN}/';

    /**
     * The Telegram's bot token.
     *
     * @var string|null
     */
    protected $bot_token;

    /**
     * The Telegram's conversation data.
     *
     * @var string|null
     */
    protected $chat;

    /**
     * The Telegram's interlocutor data.
     *
     * @var string|null
     */
    protected $sender;

    protected $typing = false;

    protected $countHideKeyboard;

    /**
     * Create a new instance.
     *
     * @param string|null $botToken
     * @param string|null $request
     */
    public function __construct(string $bot_token = null, string $request = null)
    {
        $this->bot_token = $bot_token;
        $this->setSenderFromRequest($request);
        $this->countHideKeyboard = 0;
    }

    /**
     * Set the bot webhook URL into the Telegram API
     * @param string $url New webhook URL
     */
    public function setWebhook(string $url)
    {
        return $this->bot('POST', 'setWebhook', [
            'query' => ['url' => $url]
        ]);
    }

    /**
     * Get bot information from Telegram API
     */
    public function getBotInfo()
    {
        return $this->bot('GET', 'getMe', []);
    }

    /**
     *   Sends a flag to Telegram to display a notification alert as the bot is 'writing'
     *   This method can be used to disable the notification if a 'false' parameter is received
     */
    public function showBotTyping($show = true)
    {
        if ($show === true && !$this->typing) {
            $this->typing = true;
            return $this->sendChatAction('typing');
        }
    }

    /**
     * Send an action to the user
     * @param string $action The action the user will receive
     */
    public function sendChatAction(string $action)
    {
        return $this->bot('POST', 'sendChatAction', [
            'query' => ['chat_id' => $this->chat->id, 'action' => $action]
        ]);
    }

    /**
     *   Generates a text message from a string and sends it to Telegram
     */
    public function sendTextMessage($text)
    {
        $this->sendMessage(
            ['text' => $text],
            'text'
        );
    }

    /**
     *  Sends a message to Telegram. Needs a message formatted with the Telegram notation
     */
    public function sendMessage($message, $type = 'text')
    {
        $this->showBotTyping();

        switch ($type) {
            case 'action':
                $endpoint = 'sendChatAction';
                break;

            case 'photo':
                $endpoint = 'sendPhoto';
                break;

            case 'video':
                $endpoint = 'sendVideo';
                break;

            case 'document':
                $endpoint = 'sendDocument';
                break;

            case 'text':
            default:
                $endpoint = 'sendMessage';
                $message['parse_mode'] = 'HTML';
                break;
        }
        if ($type === 'text' && isset($message['text']) && trim($message['text']) === '') {
            return true;
        }

        if (!isset($message['reply_markup']) && $this->countHideKeyboard === 0) {
            //Hide the keyboard if the response was made through text instead of a button
            $message['reply_markup'] = json_encode(['hide_keyboard' => true]);
            $this->countHideKeyboard++;
        }

        // Set the message recipient
        $message['chat_id'] = $this->chat->id;

        return $this->bot('POST', $endpoint, [
            'query' => $message
        ]);
    }

    /**
     * Send a request to the Telegram Bot API.
     *
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return ResponseInterface
     */
    protected function bot($method, $uri, array $options = [])
    {
        if (is_null($this->bot_token)) {
            throw new Exception('Bot token is not defined');
        }

        $guzzle = new Guzzle([
            'base_uri' => str_replace('{TOKEN}', $this->bot_token, $this->api_url),
        ]);

        return $guzzle->request($method, $uri, array_merge_recursive($options, []));
    }

    /**
     *  Establishes the sender data (user) from an incoming Telegram request
     */
    protected function setSenderFromRequest($request)
    {
        $data = json_decode($request);
        if (empty($data->message) && empty($data->callback_query)) {
            return;
        }

        $msg = isset($data->message) ? $data->message : $data->callback_query;
        if (isset($msg->from)) {
            $this->sender = $msg->from;
        } elseif (isset($msg->message->from)) {
            $this->sender = $msg->message->from;
        }
        if (isset($msg->chat)) {
            $this->chat = $msg->chat;
        } elseif (isset($msg->message->chat)) {
            $this->chat = $msg->message->chat;
        }
    }

    /**
     *  Establishes the sender data (user) from an incoming Telegram request
     */
    public function setSenderFromId($id)
    {
        $this->chat = (object) ['id' => $id];
    }

    /**
     *  Returns properties of the sender object when the $key parameter is provided (and exists).
     *  If no key is provided will return the whole object
     */
    public function getSender($key = null)
    {
        $sender =  (array) $this->sender;

        if ($key) {
            if (isset($sender[$key])) {
                return $sender[$key];
            }
            return null;
        } else {
            return $sender;
        }
    }

    public function getUserId()
    {
        $id = $this->getSender('id');
        return !is_null($id) ? $id : time();
    }

    /**
     *   Returns the full name of the user (first + last name)
     */
    public function getFullName()
    {
        return $this->getSender('first_name') . " " . $this->getSender('last_name');
    }

    /**
     *   Generates the external id used by HyperChat to identify one user as external.
     *   This external id will be used by HyperChat adapter to instance this client class from the external id
     */
    public function getExternalId()
    {
        return 'tg-' . $this->chat->id;
    }

    /**
     *   Retrieves the user id from the external ID generated by the getExternalId method
     */
    public static function getIdFromExternalId($externalId)
    {
        $telegramInfo = explode('-', $externalId);
        if (array_shift($telegramInfo) == 'tg') {
            return end($telegramInfo);
        }
        return null;
    }

    public static function buildExternalIdFromRequest()
    {
        $request = json_decode(file_get_contents('php://input'), true);
        $data = isset($request['message'])
            ? $request['message']
            : (isset($request['callback_query'])
                ? $request['callback_query']
                : null);
        if ($data && isset($data['chat']) && isset($data['chat']['id'])) {
            return "tg-" . $data['chat']['id'];
        } elseif ($data && isset($data['message']) && isset($data['message']['chat'])) {
            return "tg-" . $data['message']['chat']['id'];
        }
        return null;
    }


    public function getEmail()
    {
        return $this->getExternalId() . "@telegram.com";
    }

    /**
     * Generates a Telegram attachment message from a HyperChat message
     */
    public function sendAttachmentMessageFromHyperChat($message)
    {
        $type = strpos($message['type'], 'image') !== false ? 'photo'
            : (strpos($message['type'], 'video') !== false ? 'video' : 'document');

        //In order to work, remove the common ports used in Hyperchat
        $message['fullUrl'] = str_replace(":8000", "", $message['fullUrl']);
        $message['fullUrl'] = str_replace(":443", "", $message['fullUrl']);

        $this->sendMessage([
            $type => $message['fullUrl']
        ], $type);
    }

    /**
     * Get the file path from Telegram
     * @param string $fileId
     * @return string $filePath
     */
    public function getFilePathFromTelegram(string $fileId)
    {
        $filePath = "";
        $fileInfoRaw = $this->bot('GET', "getFile?file_id=".$fileId, []);
        if (method_exists($fileInfoRaw, "getBody") && method_exists($fileInfoRaw->getBody(), "getContents")) {
            $fileInfo = json_decode($fileInfoRaw->getBody()->getContents());
            if (isset($fileInfo->result) && isset($fileInfo->result->file_path)) {
                $filePath = $fileInfo->result->file_path;
            }
        }
        return $filePath;
    }

    /**
     * Get the raw file from Telegram
     * @param string $filePath
     */
    public function getFileFromTelegram(string $filePath)
    {
        $realUrl = str_replace('bot{TOKEN}', "file/bot".$this->bot_token, $this->api_url).$filePath;

        $file = explode("/", $filePath);

        $fileName = sys_get_temp_dir(). "/" . $file[1];
        $tmpFile = fopen($fileName, "w") or die;
        fwrite($tmpFile, file_get_contents($realUrl));
        
        return fopen($fileName, 'r');
    }

}
