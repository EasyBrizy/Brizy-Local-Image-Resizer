# Image Resizer

Image Resizer is a tool for resizing an uploaded image.

## Getting started

This document describes the process for running this application on your local computer.

### Check if you have installed
- `docker`
- `docker-compose`

Stops containers and removes containers, networks, volumes, and images for this project

- ```docker-compose down --remove-orphans```

Using multiple Compose files enables you to customize a Compose application for different environments or different workflows.

```docker-compose.override.yaml```

```yaml
version: '3.8'

services:
  image_resizer:
    environment:
      ORIGIN_MEDIA_URL: 'https://host/path/to/images'
```

#### Deployment

After you have successfully gone through the development process you are probably going to start a production deployment.

- ```docker build --target=production --tag=image_resizer:latest .```
- ```docker-compose -f docker-compose.production.yaml -f docker-compose.override.yaml up -d```

### Using Image Resizer

You should now have a running server! Visit ```localhost:7788``` in your browser.

##### Using as a ```getMedia``` url in [Editor Configuration](https://github.com/EasyBrizy/Brizy-Local/tree/master/packages/demo)

```
http://localhost:7788/media
```