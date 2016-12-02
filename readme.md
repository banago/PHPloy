# PHPloy
**Version 4.5**

PHPloy is an incremental Git FTP and SFTP deployment tool. By keeping track of the state of the remote server(s) it deploys only the files that were committed since the last deployment. PHPloy supports submodules, sub-submodules, deploying to multiple servers and rollbacks. PHPloy requires **PHP 5.5+** and **Git 1.8+**.

## How it works

PHPloy stores a file called `.revision` on your server. This file contains the hash of the commit that you have deployed to that server. When you run phploy, it downloads that file and compares the commit reference in it with the commit you are trying to deploy to find out which files to upload. PHPloy also stores a `.revision` file for each submodule in your repository.

## Install 

You can install PHPloy globally, in your `/usr/local/bin` directory or, locally, in your project directory. Rename `phploy.phar` to `phploy` for ease of use.

1. **Globally:** Move `phploy` into `/usr/local/bin`. Make it executable by running `sudo chmod +x phploy`.
2. **Locally** Move `phploy` into your project directory. 

## Usage 
*When using PHPloy locally, proceed the command with `php `*

1. Run `(php) phploy --init` in the terminal to create the `phploy.ini` file or create one manually.
2. Run `(php) phploy` in terminal to deploy.

Windows Users: [Installing PHPloy globally on Windows](https://github.com/banago/PHPloy/issues/214)

## phploy.ini

The `phploy.ini` file holds your project configuration. It should be located in the root directory of the project. `phploy.ini` is never uploaded to server.  Check the sample below for all available options:

```ini
; This is a sample deploy.ini file. You can specify as many
; servers as you need and use normal or quickmode configuration.
;
; NOTE: If a value in the .ini file contains any non-alphanumeric 
; characters it needs to be enclosed in double-quotes (").

[staging]
    scheme = sftp
    user = example
    ; When connecting via SFTP, you can opt for password-based authentication:
    pass = password
    ; Or private key-based authentication:
    privkey = 'path/to/or/contents/of/privatekey'
    host = staging-example.com
    path = /path/to/installation
    port = 22
    ; You can specify a branch to deploy from
    branch = develop
    ; File permission set on the uploaded files/directories
    permissions = 0700
    ; File permissions set on newly created directories
    directoryPerm = 0775
    ; Files that should be ignored and not uploaded to your server, but still tracked in your repository
    exclude[] = 'src/*.scss'
    exclude[] = '*.ini'
    ; Files that are ignored by Git, but you want to send the the server
    include[] = 'js/scripts.min.js'
    include[] = 'js/style.min.css'
    include[] = 'directory-name/'
    ; Directories that should be copied after deploy, from->to
    copy[] = 'public->www'
    ; Directories that should be purged after deploy
    purge[] = "cache/"
    ; Pre- and Post-deploy hooks
    pre-deploy[] = "wget http://staging-example.com/pre-deploy/test.php --spider --quiet"
    post-deploy[] = "wget http://staging-example.com/post-deploy/test.php --spider --quiet"
    ; Works only via SSH2 connection
    pre-deploy-remote[] = "touch .maintenance"
    post-deploy-remote[] = "mv cache cache2"
    post-deploy-remote[] = "rm .maintenance"

[production]
    quickmode = ftp://example:password@production-example.com:21/path/to/installation
    passive = true
    ssl = false
    ; You can specify a branch to deploy from
    branch = master
    ; File permission set on the uploaded files/directories
    permissions = 0774
    ; File permissions set on newly created directories
    directoryPerm = 0755
    ; Files that should be ignored and not uploaded to your server, but still tracked in your repository
    exclude[] = 'libs/*'
    exclude[] = 'config/*'
    exclude[] = 'src/*.scss'
    ; Files that are ignored by Git, but you want to send the the server
    include[] = 'js/scripts.min.js'
    include[] = 'js/style.min.css'
    include[] = 'directory-name/'
    purge[] = "cache/" 
    pre-deploy[] = "wget http://staging-example.com/pre-deploy/test.php --spider --quiet"
    post-deploy[] = "wget http://staging-example.com/post-deploy/test.php --spider --quiet"
```

If your password is missing in the `phploy.ini` file or the `PHPLOY_PASS` environment variable, PHPloy will interactively ask you for your password.
There is also an option to store the password in a file called `.phploy`.

```
[staging]
    pass="thePassword"
    
[production]
    pass="thePassword"
```

This feature is especially useful if you would like to share your phploy.ini via Git but hide your password from the public.

You can also use environment variables to deploy without storing your credentials in a file.
These variables will be used if they do not exist in the `phploy.ini` file:
```
PHPLOY_HOST
PHPLOY_PORT
PHPLOY_PASS
PHPLOY_PATH
PHPLOY_USER
```

These variables can be used like this;
```
$ PHPLOY_PORT="21" PHPLOY_HOST="myftphost.com" PHPLOY_USER="ftp" PHPLOY_PASS="ftp-password" PHPLOY_PATH="/home/user/public_html/example.com" phploy -s servername
```

Or export them like this, the script will automatically use them:
```
$ export PHPLOY_PORT="21"
$ export PHPLOY_HOST="myftphost.com"
$ export PHPLOY_USER="ftp"
$ export PHPLOY_PASS="ftp-password"
$ export PHPLOY_PATH="/home/user/public_html/example.com"
$ phploy -s servername
```

## Multiple servers

PHPloy allows you to configure multiple servers in the deploy file and deploy to any of them with ease. 

By default PHPloy will deploy to *ALL* specified servers.  Alternatively, if an entry named 'default' exists in your server configuration, PHPloy will default to that server configuration. To specify one single server, run:

    phploy -s servername

or:

    phploy --server servername
    
`servername` stands for the name you have given to the server in the `phploy.ini` configuration file.

If you have a 'default' server configured, you can specify to deploy to all configured servers by running:

    phploy --all

## Rollbacks

**Warning: the --rollback option does not currently update your submodules correctly.**

PHPloy allows you to roll back to an earlier version when you need to. Rolling back is very easy. 

To roll back to the previous commit, you just run:

    phploy --rollback

To roll back to whatever commit you want, you run:

    phploy --rollback="commit-hash-goes-here"

When you run a rollback, the files in your working copy will revert **temporarily** to the version of the rollback you are deploying. When the deployment has finished, everything will go back as it was.

Note that there is not a short version of `--rollback`.


## Listing changed files

PHPloy allows you to see what files are going to be uploaded/deleted before you actually push them. Just run: 

    phploy -l

Or:

    phploy --list

## Updating or "syncing" the remote revision

If you want to update the `.revision` file on the server to match your current local revision, run:

    phploy --sync

If you want to set it to a previous commit revision, just specify the revision like this:

    phploy --sync="your-revision-hash-here"

## Submodules

Submodules are supported, but are turned off by default since you don't expect them to change very often and you only update them once in a while. To run a deployment with submodule scanning, add the `--submodules` parameter to the command:

    phploy --submodules
    
## Purging

In many cases, we need to purge the contents of a directory after a deployment. This can be achieved by specifying the directories in `phploy.ini` like this:

    ; relative to the deployment path
    purge[] = "cache/"
    
## Hooks

PHPloy allows you to execute commands before and after the deployment. For example you can use `wget`  call a script on my server to execute a `composer update`.

    ; To execute before deployment
    pre-deploy[] = "wget http://staging-example.com/pre-deploy/test.php --spider --quiet"
    ; To execute after deployment
    post-deploy[] = "wget http://staging-example.com/post-deploy/test.php --spider --quiet"

## Logging

PHPloy supports simple logging of the activity. Logging is saved in a `phploy.log` file in your project in the following format:
    
    2016-03-28 08:12:37+02:00 --- INFO: [SHA: 59a387c26641f731df6f0d1098aaa86cd55f4382] Deployment to server: "default" from branch "master". 2 files uploaded; 0 files deleted.

To turn logging on, add this to `phploy.ini`:

    [production]
        logger = on

## Contribute

Contributions are very welcome; PHPloy is great because of the contributors. Please check out the [issues](https://github.com/banago/PHPloy/issues). 

## Credits

 * [Baki Goxhaj](https://twitter.com/banago)
 * [Contributors](https://github.com/banago/PHPloy/graphs/contributors?type=a)

## Version history

Please check [release history](https://github.com/banago/PHPloy/releases) for details.

## License

PHPloy is licensed under the MIT License (MIT).
