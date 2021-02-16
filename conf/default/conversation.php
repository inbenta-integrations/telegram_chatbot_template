<?php

//Inbenta Chatbot configuration
return array(
    "default" => array(
        "answers" => array(
            "sideBubbleAttributes"  => array(),
            "answerAttributes"      => array(
                "ANSWER_TEXT",
            ),
            "maxOptions"            => 3,
            "maxRelatedContents"    => 2,
            'skipLastCheckQuestion' => true
        ),
        "forms" => array(
            "allowUserToAbandonForm"    => true,
            "errorRetries"              => 2
        ),
        "lang"  => "en"
    ),
    "user_type" => 0,
    'source' => 'telegram', //''
    "content_ratings" => array(     // Remember that these ratings need to be created in your instance
        "enabled" => false,
        "ratings" => array(
            array(
                'id' => 1,
                'label' => 'yes',
                'comment' => false,
                'isNegative' => false
            ),
            array(
                'id' => 2,
                'label' => 'no',
                'comment' => true,   // Whether clicking this option should ask for a comment
                'isNegative' => true
            )
        )
    ),
    "digester" => array(
        "url_buttons" => array(
            "attribute_name"    => "",  // Provide the setting that contains an url+title to be displayed as URL button
            "button_title_var"  => "",  // Provide the property name that contains the button title in the button object
            "button_url_var"    => "",  // Provide the property name that contains the button URL in the button object
        ),
    ),
);
