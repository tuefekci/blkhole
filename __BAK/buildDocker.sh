#!/bin/bash
cd ./web; taskset -c 4-7 npm run build
cd ./../
taskset -c 4-7 composer update
taskset -c 4-7 docker build -t blkhole .