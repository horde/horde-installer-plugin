# Horde Installer Plugin for composer

This plugin allows composer-based installation of the Horde 6 Framework and application

## Linux

The plugin will create a var/ structure and a web/ structure in the root project.
web/ will contain symlinks of applications, javascript and themes content.
Supporting configuration for registry and applications is auto-configured.
The composer autoloader is injected into the bootstrap process.

## Windows

Windows support is preliminary. Symlinks are replaced with copies. This means you will need to run the command 
```composer horde-reconfigure ```
much more often and any wizard-generated content is overwritten with the contents of your var/config directory on any update.
Windows support is not well-tested. Patches and issues welcome.