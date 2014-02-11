# PHPloy

PHPloy is a little PHP script that allows you to deploy files through FTP to a server. It makes use of Git to know which files it should upload and which one it should delete. It is a real time-saver. PHPloy supports deployments of submodules and sub-submodules.

## Usage 
As any script, you can use PHPloy globally, from your `bin` directory or locally, from your project directory:

### Using PHPloy Globally

1. Drop `phploy` into `/usr/local/bin` and make it executable by running `sudo chmod +x phploy`.
2. Create the `deploy.ini` file.
3. Run `phploy` in terminal.

## Using PHPloy Locally

1. Drop `phploy` into your project.
2. Create the `deploy.ini` file.
3. Run `php phploy` in terminal.

## deploy.ini

The `deploy.ini` file hold your credentials and it must be in the root directory of your project. 

    ; Deploy Settings
    
    [Production]
    
    skip = false
    user = example
    pass = password    
    host = example.com
    port = 21
    path = /path/to/installation
    passive = true
    
    ; If that seemed too long for you, you can specify servers like this:
    [ftp://example:password@example.com:21/path/to/installation]

The first time it's executed, PHPloy will assume that your deployment server is empty, and will upload all the files of your project.

## View files

PHPloy allows you to check out what are going to be uploaded/deleted before you actually push them. 

Short option:

	phploy -l

Long option:

	phploy --list

## Ignore files

PHPloy ignores everything that Git ignores. But if you have files that you don't want uploaded to your server but Git is tracking them, you can ignore them by adding the following to your deploy.ini file:

	ignore_files[] = file/toignore.txt
	ignore_files[] = another/file/toignore.php

And PHPloy will ignore those files.

## How It Works

PHPloy stores a file called `.revision` on your server. This file contains the hash of the commit that you have deployed to that server. When you run phploy, it downloads that file and compares the commit reference in it with the commit you are trying to deploy to find out which files to upload.

PHPloy also stores a `.revision` file for each submodule in your repository.

##Contribute

If you've got any suggestions, questions, or anything else about PHPloy, [you should create an issue here](https://github.com/banago/PHPloy/issues). 

## Credits
PHPloy is developed by Baki Goxhaj, a [freelance WordPress and Laravel developer](http://wplancer.com) from Albania. It is based on the work of Bruno De Barros. This project was taken furtheer because the original project did not suport Git Submodues.
