@echo off
title FFPlay
:play
set /p filename=File:
ffplay -allowed_extensions ALL %filename%
goto play