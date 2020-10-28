# ArcGIS Geocode Addresses List

This package is a rewrite of the
[ArcGIS Online provider](https://github.com/geocoder-php/arcgis-online-provider)
for PHP Geocoder. Instead of "find" this uses the "geocodeAddresses" endpoint.

The reason for using this rewrite are as follows:
* find is deprecated for findAddressCandidates
* findAddressCandidates only geocodes one location per request, geocodeAddresses
can geocode multiple addresses per request
* ArcGIS World Geocoding Service prohibits storing the results without the use
of a valid ArcGIS Online token.
    * In conjunction with the token,
    findAddressCandidates also requires the "forStorage" parameter, which is not
    configurable in the ArcGIS Online provider package.
    * This package requires that you provide a valid token. Instructions for
    obtaining a valid token are found here:
    https://developers.arcgis.com/rest/geocode/api-reference/geocoding-authenticate-a-request.htm


See the [main repo](https://github.com/geocoder-php/Geocoder) for information
and documentation.

### Usage

```php
$httpClient = new \Http\Adapter\Guzzle6\Client();

// You must provide an token
$provider = new \Geocoder\Provider\ArcGISList\ArcGISList($httpClient, 'your-token');

$result = $geocoder->geocodeQuery(GeocodeQuery::create('Buckingham Palace, London'));
```

### Install

```bash
composer require wantell/arcgis-geocode-addresses
```

### Note

It is possible to specify a `sourceCountry` to restrict results to this specific
country thus reducing request time.
