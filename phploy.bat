:: To run phploy globally (from any folder), either add this folder to your system's PATH
:: or copy this BAT file somewhere into your system's PATH, eg. C:\WINDOWS
:: 
:: Note you will need PHP.exe somewhere on your system also, and if it's not also
:: in your PATH variable, you will need to specify the full path to it below
::
:: If you're not sure how to edit your system's path variable:
:: 		- Press WIN+PAUSE to open the System Control Panel screen, 
:: 		- Choose "Advanced System Settings"
::		- Click "Environment Variables"
::		- Find "Path" in the bottom section, and add the necessary folder(s) to the list, 
::		  separated by semi-colons
:: 			eg. C:\Windows;C:\Windows\System32;C:\path\to\php.exe;C:\path\to\phploy

@ECHO OFF

:: Set the console code page to use UTF-8
chcp 65001 > NUL

php C:\path\to\phploy %*
