<?php

namespace Inbenta\TelegramConnector\ExternalDigester;

class ReplyKeyboardMessage
{
    /**
     * Creates a new button for reply
     * @param string $text
     * @param array $options
     * @param bool $singleUse = true
     * @param bool $resize = false
     * @return array
     */
    public static function build(string $text, array $options, bool $singleUse = true, bool $resize = false)
    {
        return [
            'text' => $text,
            'reply_markup' => json_encode([
                'keyboard' => self::buildButtonRows($options),
                'one_time_keyboard' => $singleUse,
                'resize_keyboard' => $resize
            ])
        ];
    }

    /**
     * Build every row button
     * @param array $options
     * @return array $buttonRows
     */
    public static function buildButtonRows(array $options)
    {
        $buttonRows = [];
        foreach ($options as $button) {
            $tmpBtn = [
                'text' => $button['text']
            ];
            // Add request-location option if required
            if (isset($button['request_location']) && is_bool($button['request_location'])) {
                $tmpBtn['request_location'] = $button['request_location'];
            }
            // Add request-location option if required
            if (isset($button['request_contact']) && is_bool($button['request_contact'])) {
                $tmpBtn['request_contact'] = $button['request_contact'];
            }
            $row = isset($button['row']) ? $button['row'] : count($buttonRows);
            $buttonRows[$row][] = $tmpBtn;
        }
        return $buttonRows;
    }
}
