@echo off
rem Double-click to add "Edit newspaper metadata..." to the right-click menu of folders, for you only.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Install-ContextMenu.ps1"
pause
