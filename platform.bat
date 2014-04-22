@echo off
rem Platform CLI automatically determines the user's home directory by checking for
rem HOME or HOMEDRIVE/HOMEPATH environment variables, and the temporary
rem directory by checking for TEMP, TMP, or WINDIR environment variables.
rem The home path is used for caching Platform CLI commands and the git --reference
rem cache. The temporary directory is used by various commands, including
rem package manager for downloading projects.
rem You may want to specify a path that is not user-specific here; e.g., to
rem keep cache files on the same filesystem, or to share caches with other
rem users.

rem set HOME=H:/platform-cli
rem set TEMP=H:/platform-cli

REM See http://drupal.org/node/506448 for more information.
@php.exe "%~dp0platform" --php="php.exe" %*