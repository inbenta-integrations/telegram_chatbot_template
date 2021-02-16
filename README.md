# TELEGRAM CHATBOT INTEGRATION
 
### TABLE OF CONTENTS
* [OBJECTIVE](#objective)
* [FUNCTIONALITIES](#functionalities)
* [INSTALLATION](#installation)
* [DEPENDENCIES](#dependencies)

### OBJECTIVE
This template has been implemented in order to develop Telegram bots that consume from the 
Inbenta Chatbot API with the minimum configuration and effort. 
It uses some libraries to connect the Chatbot API with Telegram. 

The main library of this template is Telegram Connector, which extends from a base library named 
[Chatbot API Connector](https://github.com/inbenta-integrations/chatbot_api_connector), 
built to be used as a base for different external services like Skype, Line, etc.

This template includes **/conf** and **/lang** folders, which have all the configuration and 
translations required by the libraries, and a small file **server.php** which creates a 
TelegramConnector’s instance in order to handle all the incoming requests.

### FUNCTIONALITIES
This bot template inherits the functionalities from the `ChatbotConnector` library.

Currently, the features provided by this application are:

* Simple answers
* Multiple options
* Polar questions
* Chained answers
* Content ratings (yes/no + comment)
* Escalate to HyperChat after a number of no-results answers
* Escalate to HyperChat after a number of negative ratings
* Escalate to HyperChat when matching with an 'Escalation FAQ'
* Send information to webhook through forms
* Custom FAQ title in button when displaying multiple options

### INSTALLATION
It's pretty simple to get this UI working. The mandatory configuration files are included by default in `/conf/custom` to be filled in, so you have to provide the information required in these files:

* **File 'api.php'**
    Provide the API Key and API Secret of your Chatbot Instance.

* **File 'environments.php'**
    Here you can define regexps to detect `development` and `preproduction` environments. 
    If the regexps do not match the current conditions or there isn't any regex configured, 
    `production` environment will be assumed.

* **File 'telegram.php'**
    Provide the Telegram Access Token and the URL where the connector is installed.


### HOW TO CUSTOMIZE
**From configuration**

For a default behavior, the only requisite is to fill the basic configuration (more information in `/conf/README.md`). 
There are some extra configuration parameters in the configuration files that allow you to modify the basic-behavior.


**Custom Behaviors**

If you need to customize the bot flow, you need to extend the class `TelegramConnector`, 
included in the `/lib/TelegramConnector` folder. 

You can modify 'TelegramConnector' methods and override all the parent methods from `ChatbotConnector`.

For example, when the bot is configured to escalate with an agent, a conversation in HyperChat starts. If your bot needs to use an external chat service, you should override the parent method `escalateToAgent` and set up the external service:
```php
	//Tries to start a chat with an agent with an external service
	protected function escalateToAgent()
	{
		$useExternalService = $this->conf->get('chat.useExternal');
		
		if ($useExternalService) {
		    // Inform the user that the chat is being created
			$this->sendMessagesToExternal($this->buildTextMessage($this->lang->translate('creating_chat')));
			
		    // Create a new instance for the external client
		    $externalChat = New SomeExternalChatClass($this->conf->get('chat.externalConf'));
			$externalChat->openChat();
		} else {
			// Use the parent method to escalate to HyperChat
			parent::escalateToAgent();
		}
	}
```


**HyperChat escalation by no-result answer and negative content rating**

If your bot needs integration with HyperChat, fill the chat configuration at `/conf/conf-path/chat.php` 
and subscribe to the following events on your Backstage instance: 
`invitations:new`, `invitations:accept`, `forever:alone`, `chats:close`, `messages:new`. 

When subscribing to the events in Backstage, you have to point to the `/server.php` 
file in order to handle the events from HyperChat.

Configuration parameter `triesBeforeEscalation` sets the number of no-results answers after which 
the bot should escalate to an agent. Parameter `negativeRatingsBeforeEscalation` sets the number 
of negative ratings after which the bot should escalate to an agent.


**Escalation with FAQ**

If your bot has to escalate to HyperChat when matching a specific FAQ, the content needs to meet a few requisites:
- Dynamic setting named `ESCALATE`, non-indexable, visible, `Text` box-type with `Allow multiple objects` 
option checked
- In the content, add a new object to the `Escalate` setting (with the plus sign near the setting name) 
and type the text `TRUE`.

After a Restart Project Edit and Sync & Restart Project Live, your bot should escalate when this FAQ is matched.
Note that the `server.php` file has to be subscribed to the required HyperChat events as described in the
previous section.

### DEPENDENCIES
This application imports `inbenta/chatbot-api-connector` as a Composer dependency, 
that includes `symfony/http-foundation@^3.1` and `guzzlehttp/guzzle@~6.0` as dependencies too.

