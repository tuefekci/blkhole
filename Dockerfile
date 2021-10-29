FROM php:7.4-cli

COPY . /srv/blkhole
WORKDIR /srv/blkhole

CMD [ "php", "./main.php" ]

EXPOSE 1337