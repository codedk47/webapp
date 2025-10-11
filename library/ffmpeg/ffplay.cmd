@echo off
title FFPlay
:play
set /p filename=File:
cls
ffplay -hide_banner -allowed_extensions ALL %filename%
goto play