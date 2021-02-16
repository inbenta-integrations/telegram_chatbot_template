<?php

namespace Inbenta\TelegramConnector\ExternalAPI;

use Inbenta\ChatbotConnector\Utils\ConfigurationLoader;
use Inbenta\TelegramConnector\ExternalAPI\TelegramAPIClient;
use \Exception;

class SetWebhook
{
    /**
     * Make the subscription of the Webhook to the Telegram's bot
     * Request from: [server]/?subscribe=1&token=[telegramToken]
     * @param string $appPath
     * @param int $subscribe = 0
     * @param string $token = ""
     */
    public static function subscribe(string $appPath, int $subscribe = 0, string $tokenUrl = "")
    {
        if ($subscribe === 1 && trim($tokenUrl) !== "") {
            try {
                // Initialize base components
                $conf = (new ConfigurationLoader($appPath))->getConf();

                //Get values from file "telegram.php"
                $botToken = $conf->get('telegram.token');
                $url = $conf->get('telegram.url');

                if ($botToken === $tokenUrl) {
                    // Request the Telegram API to update the webhook URL
                    $tgClient = new TelegramAPIClient($botToken);                    
                    $rawResponse = $tgClient->setWebhook($url);

                    $response = json_decode($rawResponse->getBody()->getContents());

                    if (isset($response->ok) && $response->ok === true) {
                        echo "The webhook was set to <b>" . $url . "</b><br>";

                        $rawResponseInfo = $tgClient->getBotInfo();
                        $info = json_decode($rawResponseInfo->getBody()->getContents());
                        if (isset($info->result) && isset($info->result->username)) {
                            echo "Bot: <b>".$info->result->username."</b><br>";
                        }
                    } else {
                        echo 'There was an error while setting the webhook.';
                    }
                    // Display Telegram API response
                    echo "<br>Telegram response:<pre>";
                    var_dump($response);
                    echo "</pre>";
                    die;
                } else {
                    die('Error: Incorrect token');
                }
            } catch (Exception $e) {
                echo 'There was an error while setting the webhook: ' . $e->getMessage();
                die;
            }
        }
    }
}
