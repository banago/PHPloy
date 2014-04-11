# PHPloy
**Version 2.1.0**

PHPloy is a little PHP script that allows you to deploy files through FTP to a server. It makes use of Git to know which files should be uploaded and which should be deleted. It is a real time-saver. 

Unlike other similar scripts, PHPloy supports deployments of submodules and sub-submodules.


## Requirements

* PHP 5.3+ command line interpreter (CLI)
* Windows machines will require AnsiCon to display colors (and not raw color codes) in the command prompt
    1. [Download AnsiCon here](http://adoxa.hostmyway.net/ansicon/)
    2. Install it by running `ansicon -i` from a command prompt or "Run" window


## Usage 

As any script, you can use PHPloy globally, from your `bin` directory or locally, from your project directory:


### Using PHPloy locally (per project)

1. Drop `phploy` into your project.
2. Create the `deploy.ini` file.
3. Run `php phploy` in terminal.


### Using PHPloy globally in Linux

1. Drop `phploy` into `/usr/local/bin` and make it executable by running `sudo chmod +x phploy`.
2. Create the `deploy.ini` file inside your project folder.
3. Run `phploy` in terminal.


### Installing PHPloy globally in Windows

1. Extract or clone the PHPloy files into a folder of your choice
2. Ensure phploy.bat can find the path to php.exe by either:
    * Adding the path to php.exe to your system path
    * Manually adding the path inside phploy.bat
3. Add the phploy folder to your system path
4. Run `phploy` from the command prompt (from your repository folder)

Adding folders to your *system path* means that you can execute an application from any folder, and not have to specify the full path to it.  To add folders to your system path:

1. Press WINDOWS + PAUSE to open Control Panel > System screen
2. Click "Advanced System Settings"
3. Click "Environment Variables"
4. Under "System variables" there should be a variable called "Path".  Select this and click "Edit".
5. Keep the existing paths there, add a semi-colon `;` at the end and then type the location of the appropriate folder.  Spaces are OK, and no quotes are required.
6. Click OK


## deploy.ini

The `deploy.ini` file hold your credentials and it must be in the root directory of your project. Use as many servers as you need and whichever configuration type you prefer.

    ; This is a sample deploy.ini file.
    ; You can specify as many servers as you need
    ; and use whichever configuration way you like.
    ; 
    ; NOTE: If you run phploy without specifying which server to deploy to, it will deploy to ALL servers by default

    [staging]
    user = example
    pass = password
    host = staging-example.com
    path = /path/to/installation
    port = 21
    passive = true
    
    [production]
    user = example
    pass = password
    host = production-example.com
    path = /path/to/installation
    port = 21
    passive = true
    
    [quickmode]
    ; If that seemed too long for you, you can use quickmode instead
    staging = ftp://example:password@staging-example.com:21/path/to/installation
    production = ftp://example:password@production-example.com:21/path/to/installation


The first time it's executed, PHPloy will assume that your deployment server is empty, and will upload ALL the files of your project.  If the remote server already has a copy of the files, you can specify which revision it is on using the `--sync` command (see below).


## Multiple servers

PHPloy allows you to configure multiple servers in the deploy file and deploy to any of them with ease. 

By default PHPloy will deploy to *ALL* specified servers.  To specify one single server, run:

    phploy -s servername

or:

    phploy --server servername
    
`servername` stands for the name you have given to the server in the `deploy.ini` configuration file.


## Rollbacks

PHPloy allows you to roll back to an earlier version when you need to. Rolling back is very easy. 

To roll back to the previous commit, you just run:

    phploy --rollback

To roll back to whatever commit you want to, you run:

    phploy --rollback="commit-hash-goes-here"

When you run a rollback, the files in your working copy will revert **temporarily** to the version of the rollback you are deploying. When the deployment has finished, everything will go back as it was.

Note that there is not a short version of `--rollback`.


## Listing changed files

PHPloy allows you to check out what are going to be uploaded/deleted before you actually push them. Just run: 

    phploy -l

Or:

    phploy --list


## Updating or "syncing" the remote revision

If you want to update the `.revision` file on the server to match your current local revision, run:

    phploy --sync

If you want to set it to another previous commit revision, you just specify the revision like this:

    phploy --sync="your-revision-hash-here"


## How it works

PHPloy stores a file called `.revision` on your server. This file contains the hash of the commit that you have deployed to that server. When you run phploy, it downloads that file and compares the commit reference in it with the commit you are trying to deploy to find out which files to upload.

PHPloy also stores a `.revision` file for each submodule in your repository.


## Contribute

If you've got any suggestions, questions, or anything else about PHPloy, [you should create an issue here](https://github.com/banago/PHPloy/issues). 


## Version history

v2.1.0 (11 April 2014)

* Added more ANSI colours to output
* Added --debug option which provides much more verbose output
* Added --help option which displays the readme.md file
* Output now clearly indicates if it's running in --list mode
* Upload process now displays number of files in the queue (eg. "1 of 52")
* Internal: ANSI colours are now expressed through simple HTML-like tags such as <red>, <white> etc.
* Internal: git & console commands are now run from a central function to reduce code repetition and potential bugs
* Fictional: phploy now makes your coffee during a long deployment

v2.0.0-beta (April 2014)

* Added support for Windows machines by:
    * removing incompatible UTF characters
    * added phploy.bat
* Added some additional console output and reformatted some of the outputted strings for clarity
* Added command-line option --skip-subsubmodules


## Credits

PHPloy is developed by Baki Goxhaj, a [freelance WordPress and Laravel developer](http://wplancer.com) from Albania. It is based on the work of Bruno De Barros. This project was taken further because the original project did not suport Git Submodues.
