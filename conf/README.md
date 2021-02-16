### DEFAULT CONFIGURATION
Default configuration is meant to define the possible configuration files and the default values for each configuration parameter. It's not meant to be modified to configure the project, for this we'll have the `custom` configuration folder.

Every configuration file will and should be placed inside the `conf/default` folder.  This means that all configuration values should have a default representative value even if it's null. The default configuration will always be loaded at the initialization and will be overwrite by custom configuration and environment configuration.

### CUSTOM CONFIGURATION
Custom configuration folder (`conf/custom/`) contains the files that have a different configuration than the default folder. If you want to configure any parameter, just add it to the files in custom configuration folder or create the files if they don't exist (they should exist in default configuration).

### CONFIGURATION BY ENVIRONMENT
Sometimes, we may need different configurations depending on the environment.

By default, the bot is prepared to work with two environments: `production` and `development`. This means that, if you create the corresponding folder inside conf directory, the bot will look for any configuration files placed there to overwrite the `custom` or `default` configuration when it detects that it's running on that environment.

Setting the configuration for each environment is as simple as creating a folder for the desired environment (at the same level as the default folder) and placing the files we want to modify from the `custom` or `default` configuration there.
You don’t need to set the whole configuration, just the parameters that you want to modify. 

### CONFIGURATION FILES
The configuration files that will be looked for in the default folder are defined in `/conf/configurationFiles.php`.
This file contains an array where the key corresponds with the configuration namespace and the value contains the relative path for the file inside default folder. If you want to create a new configuration file you should set it’s namespace and path here and place the file inside the default folder:

```php
return [
    // Chatbot API credentials
    'api' => '/api.php',

    // Hyperchat configuration
    'chat' => '/chat.php',

    // Chatbot API conversation
    'conversation' => '/conversation.php',

    //Environments
    'environments' => '/environments.php',
    
    //My custom configuration file
    'some_new_configuration' => '/other_configuration_file.php'
];
```