@echo off
rem Double-click to remove "Edit newspaper metadata..." from the right-click menu.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Uninstall-ContextMenu.ps1"
pause
