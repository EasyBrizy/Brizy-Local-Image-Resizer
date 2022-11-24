# Install for Dev

- ```docker run --rm -v $(pwd):/app composer install```
- ```docker-composer up -d```

# Usage

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