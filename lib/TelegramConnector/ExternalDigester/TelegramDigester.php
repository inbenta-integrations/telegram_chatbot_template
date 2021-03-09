<?php

namespace Inbenta\TelegramConnector\ExternalDigester;

use \Exception;
use Inbenta\ChatbotConnector\ExternalDigester\Channels\DigesterInterface;
use Inbenta\TelegramConnector\Helpers\Helper;

class TelegramDigester extends DigesterInterface
{

    protected $conf;
    protected $channel;
    protected $session;
    protected $langManager;
    protected $externalClient;
    protected $externalMessageTypes = [
        'text',
        'callbackQuery',
        'photo',
        'attachment',
        'sticker'
    ];

    protected $attachableFormats = [
        'photo' => ['jpg', 'jpeg', 'png'],
        'document' => ['pdf', 'xls', 'xlsx', 'doc', 'docx'],
        'video' => ['mp4', 'avi'],
        'audio' => ['mp3', 'aac', 'wav', 'wma', 'ogg', 'm4a'],
        'animation' => ['gif']
    ];

    public function __construct($langManager, $conf, $session, $externalClient)
    {
        $this->langManager = $langManager;
        $this->channel = 'Telegram';
        $this->conf = $conf;
        $this->session = $session;
        $this->externalClient = $externalClient;
    }

    /**
     *  Returns the name of the channel
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     *  Checks if a request belongs to the digester channel
     */
    public static function checkRequest($request)
    {
        $request = json_decode($request);

        $isPage      = isset($request->object) && $request->object == 'page';
        $isMessaging = isset($request->entry) && isset($request->entry[0]) && isset($request->entry[0]->messaging);
        if ($isPage && $isMessaging && count((array)$request->entry[0]->messaging)) {
            return true;
        }
        return false;
    }

    /**
     *  Formats a channel request into an Inbenta Chatbot API request
     */
    public function digestToApi($request)
    {
        $request = json_decode($request);
        if (is_null($request) || (!isset($request->message) && !isset($request->callback_query))) {
            return [];
        }

        $output = [];
        if (isset($request->message->text)) {
            if ($request->message->text === "/start") {
                $output[] = ['directCall' => "sys-welcome"]; //Start commmand, welcome message from bot
            } else if ($this->session->has('escalationOptionsMap')) {
                $options = $this->session->get('escalationOptionsMap');
                $this->session->delete('escalationOptionsMap');
                $userMessage = Helper::removeAccentsToLower(trim($request->message->text));
                if (isset($options[$userMessage])) {
                    $output[] = ['escalateOption' => $options[$userMessage]];
                }
            }
        } else if (
            isset($request->message->photo) || isset($request->message->animation) || isset($request->message->document)
            || isset($request->message->video) || isset($request->message->audio)
        ) {
            $output = $this->mediaFileToHyperchat($request->message);
        }
        if (count($output) === 0) {
            $messages = [$request];
            foreach ($messages as $msg) {
                $msgType = $this->checkExternalMessageType($msg);
                $digester = 'digestFromTelegram' . ucfirst($msgType);

                //Check if there are more than one responses from one incoming message
                $digestedMessage = $this->$digester($msg);
                if (isset($digestedMessage['multiple_output'])) {
                    foreach ($digestedMessage['multiple_output'] as $message) {
                        $output[] = $message;
                    }
                } else {
                    $output[] = $digestedMessage;
                }
            }
        }
        return $output;
    }

    /**
     **  Formats an Inbenta Chatbot API response into a channel request
     **/
    public function digestFromApi($request, $lastUserQuestion = '')
    {
        //Parse request messages
        if (isset($request->answers) && is_array($request->answers)) {
            $messages = $request->answers;
        } elseif ($this->checkApiMessageType($request) !== null) {
            $messages = array('answers' => $request);
        } else {
            throw new Exception("Unknown ChatbotAPI response: " . json_encode($request, true));
        }

        $output = [];
        foreach ($messages as $msg) {
            if (!isset($msg->message) || $msg->message === "") continue;
            $msgType = $this->checkApiMessageType($msg);
            $digester = 'digestFromApi' . ucfirst($msgType);
            $digestedMessage = $this->$digester($msg, $lastUserQuestion);

            //Check if there are more than one responses from one incoming message
            if (isset($digestedMessage['multiple_output'])) {
                foreach ($digestedMessage['multiple_output'] as $message) {
                    $output[] = $message;
                }
            } else {
                $output[] = $digestedMessage;
            }
        }
        return $output;
    }

    /**
     *  Classifies the external message into one of the defined $externalMessageTypes
     */
    protected function checkExternalMessageType($message)
    {
        foreach ($this->externalMessageTypes as $type) {
            $checker = 'isTelegram' . ucfirst($type);

            if ($this->$checker($message)) {
                return $type;
            }
        }
        throw new Exception('Unknown Telegram message type');
    }

    /**
     *  Classifies the API message into one of the defined $apiMessageTypes
     */
    protected function checkApiMessageType($message)
    {
        foreach ($this->apiMessageTypes as $type) {
            $checker = 'isApi' . ucfirst($type);

            if ($this->$checker($message)) {
                return $type;
            }
        }
        return null;
    }

    /********************** EXTERNAL MESSAGE TYPE CHECKERS **********************/

    protected function isTelegramText($message)
    {
        return isset($message->message) && isset($message->message->text);
    }

    protected function isTelegramCallbackQuery($message)
    {
        return isset($message->callback_query) && isset($message->callback_query->data);
    }

    protected function isTelegramQuickReply($message)
    {
        return isset($message->message) && isset($message->message->quick_reply);
    }

    protected function isTelegramSticker($message)
    {
        return isset($message->message) && isset($message->message->attachments) && isset($message->message->sticker_id);
    }

    protected function isTelegramAttachment($message)
    {
        return isset($message->message) && isset($message->message->attachments) && !isset($message->message->sticker_id);
    }

    protected function isTelegramPhoto($message)
    {
        return isset($message->message) && isset($message->message->photo);
    }

    /********************** API MESSAGE TYPE CHECKERS **********************/

    protected function isApiAnswer($message)
    {
        return isset($message->type) && $message->type == 'answer';
    }

    protected function isApiPolarQuestion($message)
    {
        return isset($message->type) && $message->type == 'polarQuestion';
    }

    protected function isApiMultipleChoiceQuestion($message)
    {
        return isset($message->type) && $message->type == 'multipleChoiceQuestion';
    }

    protected function isApiExtendedContentsAnswer($message)
    {
        return isset($message->type) && $message->type == 'extendedContentsAnswer';
    }

    protected function hasTextMessage($message)
    {
        return isset($message->message) && is_string($message->message);
    }


    /********************** TELEGRAM MESSAGE DIGESTERS **********************/

    protected function digestFromTelegramText($message)
    {
        return ['message' => $message->message->text];
    }

    protected function digestFromTelegramCallbackQuery($message)
    {
        $data = $message->callback_query->data;
        if (json_decode($data, true)) {
            // Return array from JSON
            return json_decode($data, true);
        } else {
            // Return plain text
            return $data;
        }
    }

    protected function digestFromTelegramQuickReply($message)
    {
        $quickReply = $message->message->quick_reply;
        return json_decode($quickReply->payload, true);
    }

    protected function digestFromTelegramAttachment($message)
    {
        $attachments = [];
        foreach ($message->message->attachments as $attachment) {
            if ($attachment->type == 'location' && isset($attachment->title) && isset($attachment->url)) {
                $attachments[] = array('message' => $attachment->title . ": " . $attachment->url);
            } elseif (isset($attachment->payload) && isset($attachment->payload->url)) {
                $attachments[] = array('message' => $attachment->payload->url);
            }
        }
        return ['multiple_output' => $attachments];
    }

    protected function digestFromTelegramSticker($message)
    {
        $sticker = $message->message->attachments[0];
        return array(
            'message' => $sticker->payload->url
        );
    }

    protected function digestFromTelegramPhoto($message)
    {
        die;
    }


    /********************** CHATBOT API MESSAGE DIGESTERS **********************/

    protected function digestFromApiAnswer($message)
    {
        $output = [];
        $urlButtonSetting = isset($this->conf['url_buttons']['attribute_name'])
            ? $this->conf['url_buttons']['attribute_name']
            : '';

        if (isset($message->attributes->$urlButtonSetting) && !empty($message->attributes->$urlButtonSetting)) {
            // Send a button that opens an URL
            $output = $this->buildUrlButtonMessage($message, $message->attributes->$urlButtonSetting);
        } else if (isset($message->actionField) && !empty($message->actionField) && $message->actionField->fieldType !== 'default') {
            $output = $this->handleMessageWithActionField($message);
        }
        if (count($output) === 0) {
            if (!isset($message->messageList) && trim($message->message) !== "") {
                $message->messageList = [$message->message];
            } else if ((is_array($message->messageList) && count($message->messageList) == 0) || !is_array($message->messageList)) {
                $message->messageList = [""];
            }
            if (isset($message->attributes->SIDEBUBBLE_TEXT) && trim($message->attributes->SIDEBUBBLE_TEXT) !== "") {
                $countMessages = count($message->messageList);
                $message->messageList[$countMessages] = $message->attributes->SIDEBUBBLE_TEXT;
            }

            $output['multiple_output'] = [];
            foreach ($message->messageList as $messageTxt) {
                $messageTxt = $this->processHtml($messageTxt);

                $outputTmp = $this->handleMessageWithImages($messageTxt);
                $outputTmp2 = [];
                foreach ($outputTmp as $key => $element) {
                    if (isset($element["text"])) {
                        $outputTmp[$key]["text"] = $this->handleBreakLine($element["text"]);
                        if (strpos($outputTmp[$key]["text"], '<iframe') !== false) {
                            $outputTmp2 = $this->handleMessageWithIframe($outputTmp[$key]["text"]);
                            if (count($outputTmp2) > 0) {
                                unset($outputTmp[$key]);
                            }
                        }
                    }
                }
                $outputTmp = array_merge($outputTmp, $outputTmp2);
                $output['multiple_output'] = array_merge($output['multiple_output'], $outputTmp);
            }
            if (count($output['multiple_output']) > 0) {
                $output['multiple_output'] = $this->handleMessageWithRelatedContent($message, $output['multiple_output']);
            } else {
                $output = [];
            }
        }
        return $output;
    }

    protected function digestFromApiPolarQuestion($message, $lastUserQuestion)
    {
        $buttonOptions = [];
        foreach ($message->options as $option) {
            $buttonOptions[] = [
                'text' => $this->langManager->translate($option->label),
                'payload' => ['option' => $option->value]
            ];
        }
        $response = ReplyKeyboardMessage::build($message->message, $buttonOptions);
        return $response;
    }

    protected function digestFromApiExtendedContentsAnswer($message)
    {
        $buttonOptions = [];
        $countOptions = 0;
        foreach ($message->subAnswers as $index => $option) {
            $buttonOptions[$countOptions] = [
                'text' => $option->attributes->title,
                'payload' => ['extendedContentAnswer' => $index]
            ];
            if (isset($option->parameters) && isset($option->parameters->contents) && isset($option->parameters->contents->url)) {
                $buttonOptions[$countOptions]['url'] = $option->parameters->contents->url->value;
            }
            $countOptions++;
        }
        $response = InlineKeyboardMessage::build($message->message, $buttonOptions);
        return $response;
    }

    protected function digestFromApiMultipleChoiceQuestion($message, $lastUserQuestion)
    {
        $isDirectCall = true;
        $buttonOptions = [];
        foreach ($message->options as $option) {
            $buttonOptions[] = ['text' => $option->label];

            if (!isset($option->revisitableLink) || !$option->revisitableLink) {
                $isDirectCall = false;
                $payload = $option->value;
            } else {
                $payload  = $option->revisitableLink;
            }
        }
        $response = ReplyKeyboardMessage::build($message->message, $buttonOptions);

        return $response;
    }

    /********************** MISC **********************/

    public function buildContentRatingsMessage($ratingOptions, $rateCode)
    {
        $buttonOptions = [];
        $optionsMapping = [];
        foreach ($ratingOptions as $option) {
            $tmpBtn = [
                'text' => $this->langManager->translate($option['label'])
            ];
            $buttonOptions[] = $tmpBtn;
            $optionsMapping[$tmpBtn['text']] = [
                'askRatingComment' => isset($option['comment']) && $option['comment'],
                'isNegativeRating' => isset($option['isNegative']) && $option['isNegative'],
                'ratingData' => [
                    'type' => 'rate',
                    'data' => array(
                        'code'    => $rateCode,
                        'value'   => $option['id'],
                        'comment' => null
                    )
                ]
            ];
        }
        $this->session->set('ratingOptionsMap', $optionsMapping);

        $message = $this->langManager->translate('rate_content_intro');
        $response = ReplyKeyboardMessage::build($message, $buttonOptions);
        return $response;
    }

    /**
     *  Splits a message that contains an <img> tag into text/image/text and displays them in Telegram
     */
    protected function handleMessageWithImages($message)
    {
        //Capture all IMG tags and return an array with [text,imageURL,text,...]
        $parts = preg_split('/<\s*img.*?src\s*=\s*"(.+?)".*?\s*\/?>/', $message, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $elements = [];
        for ($i = 0; $i < count($parts); $i++) {
            if (substr($parts[$i], 0, 4) == 'http') {
                $elements[] = ['type' => 'action', 'action' => 'upload_photo'];
                $elements[] = ['type' => 'photo', 'photo' => $parts[$i]];
            } else {
                $elements[]['text'] = $parts[$i];
            }
        }
        return $elements;
    }

    /**
     * Extracts the url from the iframe
     * @param string $messageTxt
     * @return array $elements
     */
    private function handleMessageWithIframe(string $messageTxt)
    {
        //Capture all IFRAME tags and return an array with [text,IFRAME,text,...]
        $parts = preg_split('/<\s*iframe.*?src\s*=\s*"(.+?)".*?\s*\/?>/', $messageTxt, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $elements = [];
        for ($i = 0; $i < count($parts); $i++) {
            if (strpos($parts[$i], "</iframe>") > 0) {
                continue;
            }
            if (substr($parts[$i], 0, 4) == 'http') {
                $urlElements = explode(".", $parts[$i]);
                $fileFormat = $urlElements[count($urlElements) - 1];

                $hasMedia = false;
                foreach ($this->attachableFormats as $type => $formats) {
                    if (in_array($fileFormat, $formats)) {
                        $elements[] = ['type' => 'action', 'action' => 'upload_' . $type];
                        $elements[] = ['type' => $type, $type => $parts[$i]];
                        $hasMedia = true;
                        break;
                    }
                }
                if (!$hasMedia) {
                    $pos1 = strpos($messageTxt, "<iframe");
                    $pos2 = strpos($messageTxt, "</iframe>", $pos1);
                    $iframe = substr($messageTxt, $pos1, $pos2 - $pos1 + 9);
                    $elements[]["text"] = str_replace($iframe, "<a href='" . $parts[$i] . "'>" . $this->langManager->translate("link") . "</a>", $messageTxt);
                }
            } else {
                $elements[]["text"] = $parts[$i];
            }
        }
        foreach ($elements as $key => $element) {
            if (isset($element["text"])) {
                $elements[$key]["text"] = str_replace("</iframe>", "", $element["text"]);
            }
        }
        return $elements;
    }

    /**
     * Replace <p> tags with \n
     * @param string $messageTxt
     * @return string $messageTxt
     */
    private function handleBreakLine(string $messageTxt)
    {
        $messageTxt = str_replace("<p>", "\n\n", $messageTxt);
        $messageTxt = str_replace("</p>", "", $messageTxt);
        return $messageTxt;
    }

    /**
     *  Sends the text answer and displays an URL button
     */
    protected function buildUrlButtonMessage($message, $urlButton)
    {
        $buttonTitleProp = $this->conf['url_buttons']['button_title_var'];
        $buttonURLProp = $this->conf['url_buttons']['button_url_var'];

        if (!is_array($urlButton)) {
            $urlButton = [$urlButton];
        }

        $buttons = [];
        foreach ($urlButton as $button) {
            // If any of the urlButtons has any invalid/missing url or title, abort and send a simple text message
            if (!isset($button->$buttonURLProp) || !isset($button->$buttonTitleProp) || empty($button->$buttonURLProp) || empty($button->$buttonTitleProp)) {
                return ['text' => strip_tags($message->message)];
            }
            $buttons[] = [
                'text' => $button->$buttonTitleProp,
                'url' => $button->$buttonURLProp
            ];
        }

        return InlineKeyboardMessage::build($message->message, $buttons);
    }

    public function buildEscalationMessage()
    {
        $escalateOptions = [
            ['label' => 'yes', 'escalate' => true],
            ['label' => 'no', 'escalate' => false]
        ];

        $buttonOptions = [];
        $optionsMapping = [];
        foreach ($escalateOptions as $option) {
            $tmpBtn = [
                'text' => $this->langManager->translate($option['label']),
            ];
            $buttonOptions[] = $tmpBtn;
            $optionsMapping[Helper::removeAccentsToLower($tmpBtn['text'])] = $option['escalate'];
        }
        $this->session->set('escalationOptionsMap', $optionsMapping);

        $message = $this->langManager->translate('ask_to_escalate');
        $response = ReplyKeyboardMessage::build($message, $buttonOptions);
        return $response;
    }


    /**
     * Validate if the message has action fields
     * @param object $message
     * @param array $output
     * @return array $output
     */
    protected function handleMessageWithActionField(object $message)
    {
        $output = [];
        if (isset($message->actionField) && !empty($message->actionField)) {
            if ($message->actionField->fieldType === 'list') {
                $output = $this->handleMessageWithListValues($message->message, $message->actionField->listValues);
            } elseif ($message->actionField->fieldType === 'datePicker') {
                $output['text'] = strip_tags($message->message . " (date format: mm/dd/YYYY)");
            }
        }
        return $output;
    }

    /**
     * Set the options for message with list values
     * @param string $message
     * @param object $listValues
     * @return array $output
     */
    protected function handleMessageWithListValues(string $message, object $listValues)
    {
        $output = [];
        $buttonOptionList = [];

        $buttonOptionList = [];
        foreach ($listValues->values as $index => $option) {
            $buttonOptionList[] = [
                'text' => strip_tags($option->label[0]),
                'payload' => [
                    'option' => $option->label[0]
                ]
            ];
            if ($index == 6) break;
        }
        if (count($buttonOptionList) > 0) {
            $output = InlineKeyboardMessage::build($message, $buttonOptionList);
        }
        return $output;
    }

    /**
     * Validate if the message has related content and put like an option list
     * @param object $message
     * @param array $output
     * @return array $output
     */
    protected function handleMessageWithRelatedContent(object $message, array $output)
    {
        if (isset($message->parameters->contents->related->relatedContents) && !empty($message->parameters->contents->related->relatedContents)) {
            $buttonRelatedContent = [];
            foreach ($message->parameters->contents->related->relatedContents as $relatedContent) {
                $buttonRelatedContent[] = [
                    'text' => $relatedContent->title,
                    'payload' => [
                        'message' => $relatedContent->title
                    ]
                ];
            }
            if (count($buttonRelatedContent) > 0) {
                $title = $message->parameters->contents->related->relatedTitle;
                $output[] = InlineKeyboardMessage::build($title, $buttonRelatedContent);
            }
        }
        return $output;
    }

    /**
     * Keep the HTML tags valide on Telegram
     * ("li", "ul", "ol", "p". "img", "p" and "iframe" are not valid tags but they are needed and parsed in the next methods)
     * @param string $text
     * @return string $content
     */
    public function processHtml(string $text): string
    {
        $content = str_replace(["\r\n", "\r", "\n", "\t"], "", $text);
        $content = strip_tags($content, "<br><b><strong><em><i><ins><u><del><strike><s><code><pre><a></a><li><ul><ol><p><img><iframe>");
        $content = str_replace("&nbsp;", " ", $content);
        $content = str_replace(["<br>", "<br/>", "<br />"], "\n\n", $content);
        $content = str_replace(["<li>", "</li>"], ["\n-", ""], $content);
        $content = str_replace(["<ul>", "<ol>"], "", $content);
        $content = str_replace(["</ul>", "</ol>"], ["\n", "\n"], $content);
        return $content;
    }

    /**
     * Check if Hyperchat is running and if the attached file is correct
     * @param object $request
     * @return array $output
     */
    protected function mediaFileToHyperchat(object $request)
    {
        $output = [];
        if ($this->session->get('chatOnGoing', false)) {
            $fileId = "";
            if (isset($request->photo)) {
                $fileId = $request->photo[1]->file_id;
            } else if (isset($request->animation)) {
                $fileId = $request->animation->file_id;
            } else if (isset($request->document)) {
                $fileId = $request->document->file_id;
            } else if (isset($request->video)) {
                $fileId = $request->video->file_id;
            } else if (isset($request->audio)) {
                $fileId = $request->audio->file_id;
            } else {
                return $output; //No file type found, return empty array
            }

            $mediaFile = $this->getMediaFile($fileId);
            if ($mediaFile !== "") {
                if (isset($request->caption) && trim($request->caption) !== "") {
                    $output[] = ['message' => $request->caption];
                }
                $output[] = ['media' => $mediaFile];
            }
        }
        return $output;
    }

    /**
     * Get the media file from the Telegram response, 
     * save file into temporal directory to sent to Hyperchat
     * @param string $fileId
     */
    protected function getMediaFile(string $fileId)
    {
        $filePath = $this->externalClient->getFilePathFromTelegram($fileId);
        if ($filePath !== "") {
            $formatTmp = explode(".", $filePath);
            if (count($formatTmp) > 0) {
                $fileFormat = $formatTmp[count($formatTmp) - 1];
                foreach ($this->attachableFormats as $formats) {
                    if (in_array($fileFormat, $formats)) {
                        return $this->externalClient->getFileFromTelegram($filePath);
                    }
                }
            }
        }
        return "";
    }
}
