# PHPloy

PHPloy is a little PHP script that allows you to deploy files through FTP to a server. It makes use of Git to know which files it should upload and which one it should delete. It is a real time-saver. PHPloy supports deployments of submodules and sub-submodules.

## Usage 
As any script, you can use PHPloy globally, from your `bin` directory or locally, from your project directory:

### Using PHPloy Globally

1. Drop `phploy` into `/usr/local/bin` and make it executable by running `sudo chmod +x phploy`.
2. Create the `deploy.ini` file.
3. Run `phploy` in terminal.

### Using PHPloy Locally

1. Drop `phploy` into your project.
2. Create the `deploy.ini` file.
3. Run `php phploy` in terminal.

## deploy.ini

The `deploy.ini` file hold your credentials and it must be in the root directory of your project. Use as many servers as you need and whichever configuration type you prefer.

    ; This is a sample deploy.ini file.
    ; You can specify as many servers as you need
    ; and use whichever configuration way you like.

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
    
    ; If that seemed too long for you, you can use quickmode instead:
    [quickmode]
        staging = ftp://example:password@staging-example.com:21/path/to/installation
        production = ftp://example:password@production-example.com:21/path/to/installation


The first time it's executed, PHPloy will assume that your deployment server is empty, and will upload all the files of your project.

## Multiple Servers

PHPloy allows you to configure multilple servers in the deploy file and deploy to any of them with ease. By default it will deploy to all specified servers.
To specify one server run: 

    phploy -s servername

Or:

    phploy --server servername
    
`servername` stands for the name you have given to the server in the `deploy.ini` configuration file.

## Rollbacks

PHPloy allows you to roll back to an earlier version when you need to. Rolling back is very easy. 

To roll back to the previous commit, you just run:

    phploy --rollback

To roll back to whatever commit you want to, you run:

    phploy --rollback="commit-hash-goes-here"

Note that there is not a short version of `--rollback` and also that when you run a rollback your files will revert **temporarely** to the version of the rollback you are running. When the deploymnet is finished, everything will go back as it was. 

## View files

PHPloy allows you to check out what are going to be uploaded/deleted before you actually push them. Just run: 

    phploy -l

Or:

    phploy --list

## How It Works

PHPloy stores a file called `.revision` on your server. This file contains the hash of the commit that you have deployed to that server. When you run phploy, it downloads that file and compares the commit reference in it with the commit you are trying to deploy to find out which files to upload.

PHPloy also stores a `.revision` file for each submodule in your repository.

##Contribute

If you've got any suggestions, questions, or anything else about PHPloy, [you should create an issue here](https://github.com/banago/PHPloy/issues). 

## Credits
PHPloy is developed by Baki Goxhaj, a [freelance WordPress and Laravel developer](http://wplancer.com) from Albania. It is based on the work of Bruno De Barros. This project was taken furtheer because the original project did not suport Git Submodues.
