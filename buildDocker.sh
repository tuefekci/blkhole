#!/bin/bash
cd ./web; npm run build
cd ./../
composer update
docker build -t blkhole .