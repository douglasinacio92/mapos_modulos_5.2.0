@echo off
rem Para o agente de impressao e tira ele da inicializacao do Windows.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0desinstalar.ps1"
pause
