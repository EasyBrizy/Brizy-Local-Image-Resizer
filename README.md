# Install for Dev

- ```docker run --rm -v $(pwd):/app composer install```
- ```docker-composer up -d```

# Usage

### Resize endpoint
```
POST http://CONTAINER_HOST/resize
Content-Type: multipart/form-data; boundary=WebAppBoundary

--WebAppBoundary
Content-Disposition: form-data; name="image"; filename="test.png"

< ./path/to/file/test.png
--WebAppBoundary--

--WebAppBoundary
Content-Disposition: form-data; name="filter";

iW=400&iH=any
--WebAppBoundary--
```

### Get an image by url params
```
GET http://CONTAINER_HOST/media/iW=1000&iH=any/d03-phones-mock/image.png
```