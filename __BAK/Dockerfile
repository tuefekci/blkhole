FROM php:7.4-cli

COPY . /srv/blkhole
WORKDIR /srv/blkhole

CMD [ "env >> /srv/blkhole/.env"]
CMD [ "php", "./main.php" ]

EXPOSE 1337