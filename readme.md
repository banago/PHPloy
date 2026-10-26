# PHPloy
**Version 5.0.0**

PHPloy is an incremental Git FTP and SFTP deployment tool. By keeping track of the state of the remote server(s) it deploys only the files that were committed since the last deployment. PHPloy supports deploying to multiple servers and rollbacks. Submodule support is deprecated — see [Submodules](#submodules). PHPloy requires **PHP 8.0+** (PHP 8.4+ for development/testing) and **Git 1.8+**.

## What's New in 5.0.0

- **Improved File Structure**: Better organized codebase with clear separation of concerns
- **Flysystem 3.0 Upgrade**: Enhanced file system abstraction with latest Flysystem version
- **Modern Testing Suite**: 
  - Replaced legacy Vagrant setup with Docker Compose
  - Migrated from PHPUnit to Pest PHP for more expressive tests
  - Added FTP and SFTP test servers for comprehensive integration testing
- **Code Quality Tools**:
  - PSR-12 coding standards enforcement
  - Static analysis with PHPStan
  - Automated code style fixing

## How it works

PHPloy stores a file called `.revision` on your server. This file contains the hash of the commit that you have deployed to that server. When you run phploy, it downloads that file and compares the commit reference in it with the commit you are trying to deploy to find out which files to upload. PHPloy also stores a `.revision` file for each submodule in your repository.

## Install 

### Via Composer

If you have composer installed in your machine, you can pull PHPloy globally like this:

```bash
composer global require "banago/phploy"
```

Make sure to place the `$HOME/.composer/vendor/bin` directory (or the [equivalent directory](http://stackoverflow.com/a/40470979/512277) for your OS) 
in your `$PATH` so the PHPloy executable can be located by your system.

### Via Phar Archive

You can install PHPloy via a phar archive:

1. **Download**: Get the latest `phploy.phar` from [GitHub Pages](https://banago.github.io/PHPloy/phploy.phar)
2. **Install**:
   - **Globally:** Move to `/usr/local/bin/phploy` and make executable:
     ```bash
     sudo mv phploy.phar /usr/local/bin/phploy
     sudo chmod +x /usr/local/bin/phploy
     ```
   - **Locally:** Move to your project directory and use as:
     ```bash
     php phploy.phar
     ```

## Usage 

*When using PHPloy locally, proceed the command with `php `*

1. Run `phploy --init` in the terminal to create the `phploy.ini` file or create one manually.
2. Run `phploy` in terminal to deploy.

Windows Users: [Installing PHPloy globally on Windows](https://github.com/banago/PHPloy/issues/214)

## phploy.ini

The `phploy.ini` file holds your project configuration. It should be located in the root directory of the project and is never uploaded to the server.

A minimal example:

```ini
[production]
    quickmode = ftp://user:pass@example.com:21/path/to/installation
```

The full annotated sample — with every available option documented inline — lives in [`phploy.ini`](phploy.ini) at the repo root. Run `phploy --init` in your project to generate it.

If your password is missing in the `phploy.ini` file or the `PHPLOY_PASS` environment variable, PHPloy will interactively ask you for your password.
There is also an option to store the user and password in a file called `.phploy`.

```
[staging]
    user="theUser"
    pass="thePassword"
    
[production]
    user="theUser"
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
PHPLOY_PRIVKEY
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
$ export PHPLOY_PRIVKEY="path/to/or/contents/of/privatekey"
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

## Shared configuration (custom defaults)

If you specify a server configuration named `*`, all options configured in this section will be shared with other 
servers. This basically allows you to inject custom default values.

```ini
; The special '*' configuration is shared between all other configurations (think include)
[*]
    exclude[] = 'src/*'
    include[] = "dist/app.css"

; As a result both shard1 and shard2 will have the same exclude[] and include[] "default" values
[shard1]
    quickmode = ftp://example:password@shard1-example.com:21/path/to/installation

[shard2]
    quickmode = ftp://example:password@shard2-example.com:21/path/to/installation
```

## Rollbacks

**Warning: the --rollback option does not currently update your submodules correctly.**

PHPloy allows you to roll back to an earlier version when you need to. Rolling back is very easy. 

To roll back interactively, run `--rollback` with no value and pick a commit from an arrow-key list of your recent commits:

    phploy --rollback

To roll back to a specific commit, pass its hash:

    phploy --rollback commit-hash-goes-here

When you run a rollback, the files in your working copy will revert **temporarily** to the version of the rollback you are deploying. When the deployment has finished, everything will go back as it was.

Note that there is not a short version of `--rollback`.


## Listing changed files

PHPloy allows you to see what files are going to be uploaded/deleted before you actually push them. Just run: 

    phploy -l

Or:

    phploy --list

## Checking deployment status

PHPloy can report the state of a server without deploying anything: the remote revision, how many commits you are behind, and how many files the next deploy would upload and delete:

    phploy --status

If the server has never been deployed, the revision is reported as `(none - not yet deployed)`.

## Updating or "syncing" the remote revision

If you want to update the `.revision` file on the server to match your current local revision, run:

    phploy --sync

If you want to set it to a previous commit revision, just specify the revision like this:

    phploy --sync your-revision-hash-here

## Creating deployment directory on first deploy

If the deployment directory does not exist, you can instruct PHPloy to create it for you:

    phploy --force

## Manual fresh upload

If you want to do a fresh upload, even if you have deployed earlier, use the `--fresh` argument like this:

    phploy --fresh

## Resuming an interrupted deploy

If a deploy is interrupted mid-list — a dropped connection, a lost network, a stopped process — PHPloy aborts loudly with the file index it stopped at and does not advance the server revision, so a plain re-run resumes correctly. To skip re-uploading the files that already landed, pass the 1-based index from the error message to `--from`:

    phploy --from 599

This re-uploads file 599 (in case it was only partially transferred) and continues from there.

## Submodules

> **⚠️ Deprecated.** Submodule support is deprecated and will be removed in a future major version. Composer is the recommended way to manage dependencies in PHP projects. If you actively use `--submodules`, please [open an issue](https://github.com/banago/PHPloy/issues) so we can gauge real-world usage before removal.

Submodules are supported, but are turned off by default since you don't expect them to change very often and you only update them once in a while. To run a deployment with submodule scanning, add the `--submodules` parameter to the command:

    phploy --submodules
    
## Purging

In many cases, we need to purge the contents of a directory after a deployment. This can be achieved by specifying the directories in `phploy.ini` like this:

    ; relative to the deployment path
    purge[] = "cache/"

To purge a directory before deployment, specify the directories in `phploy.ini` like this:

    ; relative to the deployment path
    purge-before[] = "dist/"
    
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
