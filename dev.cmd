@echo off
setlocal
cd /d "%~dp0"
node tests\Frontend\cms-editor-preview.mjs --manual %*
exit /b %errorlevel%
