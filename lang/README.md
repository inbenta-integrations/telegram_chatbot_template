### STRUCTURE
The lang folder should contain a file for each language that the bot will speak. The name of the file should match the value specified to the bot in `/conf/current-conf-folder/conversation.php` at `default/lang` parameter. The values accepted are described in Chatbot API Routes `/conversation`

Here is an example of a lang file:
```php
    return array(
    	'agent_joined' => 'Agent $agentName has joined the conversation.',
    	'chat_closed' => 'Chat has been closed.',
    	'no_agents' => 'Sorry, there are no agents available. Can you retry in a few minutes?',
    	'creating_chat' => 'I will try to connect you with an agent. Please wait.',
    	'yes' => 'Yes',
    	'no' => 'No'
    );
```