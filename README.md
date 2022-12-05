# Image Resizer

Image Resizer is a tool for resizing an uploaded image.

## Getting started

This document describes the process for running this application on your local computer.

### Check if you have installed
- `docker`
- `docker compose`

Stops containers and removes containers, networks, volumes, and images for this project

- ```docker-compose down --remove-orphans```

#### Development mode

Once you've installed Docker && Docker Compose, open Terminal and run the following:

- ```docker run --rm -v $(pwd):/app composer install --ignore-platform-reqs```
- ```docker-compose -f docker-compose.yaml up -d```

Make sure ```var/log``` && ```var/cache``` are writable

You should now have a running server! Visit ```localhost:7788``` in your browser.

#### Production mode

After you have successfully gone through the development process you are probably going to start a production deployment.

- ```docker build --target=production --tag=image-resizer:latest .```
- ```docker-compose -f docker-compose.production.yaml up -d```

You should now have a running server! Visit ```localhost:7789``` in your browser.


### Using as a ```getMedia``` url in [Editor Configuration](https://github.com/EasyBrizy/Brizy-Local/tree/master/packages/demo)

```
http://localhost:7789/media
```