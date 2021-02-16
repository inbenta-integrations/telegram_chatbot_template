<?php

namespace Inbenta\TelegramConnector\ExternalDigester;

class InlineKeyboardMessage
{

    /**
     * Build an inline buttons (inside the conversation)
     * @param string $text
     * @param array $options
     */
    public static function build(string $text, array $options)
    {
        return [
            'text' => $text,
            'reply_markup' => json_encode([
                'inline_keyboard' => self::buildButtonRows($options)
            ])
        ];
    }

    /**
     * Build every button
     * @param array $options
     */
    public static function buildButtonRows(array $options)
    {
        $buttonRows = [];
        foreach ($options as $button) {
            $tmpBtn = [
                'text' => $button['text'],
                'callback_data' => json_encode($button['payload'], true)
            ];
            // Add URL if it's an url_button
            if (isset($button['url']) && $button['url'] !== '') {
                $tmpBtn['url'] = $button['url'];
            }
            // Add callback data if it's a callback button
            if (isset($button['callback']) && $button['callback'] !== '') {
                $tmpBtn['callback_data'] = $button['callback'];
            }
            $row = isset($button['row']) ? $button['row'] : count($buttonRows);
            $buttonRows[$row][] = $tmpBtn;
        }
        return $buttonRows;
    }
}
