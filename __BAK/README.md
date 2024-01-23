# blkhole

Blkhole serves as a download management tool designed for AllDebrid cloud downloads, facilitating the seamless automation of downloads directly to your local network.

Key features include:
- A user-friendly web interface for the management of <a href="https://alldebrid.com/?uid=2rp0k&lang=en">alldebrid.com</a> downloads, cloud Torrent, Magnet, and DDL links.
- Automated categorization-based downloading of completed cloud tasks to the local file system.
- Download enhancements and optimizations.

![alt text](https://github.com/tuefekci/blkhole/raw/main/web/src/screenshot.png "Web Interface")



---

# Getting Started

Be aware config and env vars will only be used if the var is not initialized in the store so if you want to reset the app completely you need to delete store.blk in data/config folder.

blkhole has no security options because it is intended to be run in your local network. If you want to use it in a public network you need to setup your own security, for example reverse nginx with password protection.

### Features
- Magnet links
- DDL links (based on available DDL services by provider)
- Torrents
- Chunked Downloads
- Resumable Downloads
- Bandwidth Limitation 
- Connection Limitation 
- Automatic cleanup of finished downloads (additional to the cleanup of finished tasks in the cloud)
- Automatic cleanup of failed downloads (additional to the cleanup of failed tasks in the cloud)
- Automatic cleanup of old files (files which are not active downloads will be removed after 24 hours)

---

## Settings

#### download
- parallel=3 # Number of parallel downloads
- connections=3 # Number of connections per download
- bandwidth=3072 # Max bandwidth in B/s
- chunkSize=3200 # Chunk size in B (faster connection should have bigger chunk size slower smaller this is specific to your network mostly)

### Docker
- Have a look at Docker-Compose file and edit it to your preferences.
- Everything in the Config.ini can also be used in the ENV Vars Section just combine header with the key all in uppercase for example: ALLDEBRID_APIKEY or WEBINTERFACE_PORT
- run docker-compose with your compose file or import to Portainer etc.

### Standalone PHP > 7.4 (older versions might work but i am not testing them)
- Copy data/config/config_example.ini add your alldebrid api key etc. and rename to config.ini.
- run composer update
- run php main.php

----

## Screenshots
![alt text](https://github.com/tuefekci/blkhole/raw/main/web/src/screenshot.png "Web Interface")

---

## Tested with
- Radarr
- Sonarr

---

## Todo (Goals):
- Self Updating
- Unpack Archives after Download & CRC Check
- Setup a proper color scheme for the web interface
- Add Multi Provider / Multi Source Support

---

## AllDebrid
[<img src="https://cdn.alldebrid.com/lib/images/features.en.gif">](https://alldebrid.com/?uid=2rp0k&lang=en)

AllDebrid is a site that allow to generate links and download speeds from more than 71 file hosts and 995 streaming platforms, without having to pay a premium account on each file hosters.
AllDebrid offer multiple tools to make it easier for you: addon for browsers, streaming, torrent file conversion, support for multiple download managers such as Jdownloader, and more!
No more limitations from the file hosters, enjoy your downloads at full speed!

[Register for AllDebrid with my invite link and support me](https://alldebrid.com/?uid=2rp0k&lang=en)

---

## Similar Projects
- [premiumizer for premiumize](https://github.com/piejanssens/premiumizer)

