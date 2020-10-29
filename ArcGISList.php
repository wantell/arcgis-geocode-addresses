<?php

declare(strict_types=1);

/**
 * This file is a modified version of geocoder-php/arcgis-online-provider,
 * modified to use the geocodeAddresses endpoint:
 * https://developers.arcgis.com/rest/geocode/api-reference/geocoding-geocode-addresses.htm
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\ArcGISList;

use Geocoder\Collection;
use Geocoder\Exception\InvalidArgument;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\ArcGISOnline;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Http\Provider\AbstractHttpProvider;
use Geocoder\Provider\Provider;
use Http\Client\HttpClient;

/**
 * @author ALKOUM Dorian <baikunz@gmail.com>
 */
final class ArcGISList extends AbstractHttpProvider implements Provider
{
    /**
     * @var string
     */
    const ENDPOINT_URL = 'https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/geocodeAddresses?token=%s&addresses=%s';

    /**
     * @var string
     *
     * Currently valid ArcGIS World Geocoding Service token.
     * https://developers.arcgis.com/rest/geocode/api-reference/geocoding-authenticate-a-request.htm
     */
    private $token;

    /**
     * @var string
     */
    private $sourceCountry;

    /**
     * ArcGIS World Geocoding Service
     * https://developers.arcgis.com/rest/geocode/api-reference/overview-world-geocoding-service.htm
     *
     * @param HttpClient $client        An HTTP adapter
     * @param string     $token         Your authentication token
     * @param string     $client_id     Your authentication token
     * @param string     $client_secret Your authentication token
     * @param string     $sourceCountry Country biasing (optional)
     *
     * @return GoogleMaps
     */
    public static function token(
        HttpClient $client,
        string $token,
        string $sourceCountry = null
    ) {
        $provider = new self($client, $token, $sourceCountry);

        return $provider;
    }

    /**
     * @param HttpClient $client        An HTTP adapter
     * @param string     $token
     * @param string     $sourceCountry Country biasing (optional)
     */
    public function __construct(HttpClient $client, string $token, string $sourceCountry = null)
    {
        parent::__construct($client);

        $this->token = $token;
        $this->sourceCountry = $sourceCountry;
    }

    /**
     * {@inheritdoc}
     */
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $address = $query->getText();
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The ArcGISList provider does not support IP addresses, only street addresses.');
        }

        // Save a request if no valid address entered
        if (empty($address)) {
            throw new InvalidArgument('Address cannot be empty.');
        }

        // Build the addresses parameter json, using the SingleLine attribute
        // for the $address value provided by $query.
        //
        // Even though we can send up to 1,000 addresses for bulk geocoding,
        // $query will only contain one address.
        // If this were to change, the commented code following this declaration
        // would be used.
        $addresses = [
          'records' => [
            [
              'attributes' => [
                'OBJECTID' => 1,
                'SingleLine' => $address,
              ],
            ],
          ],
        ];
        // $addresses = [
        //   'records' => [],
        // ];
        // $i = 1;
        // foreach ($ADDRESS_COLLECTION as $address) {
        //   $addresses['records'][] = [
        //     'attributes' => [
        //       'OBJECTID' => $i++,
        //       'SingleLine' => $address,
        //     ],
        //   ];
        // }

        $url = sprintf(
          self::ENDPOINT_URL,
          $this->token,
          urlencode(json_encode($addresses))
        );
        $json = $this->executeQuery($url, $query->getLimit());

        // no result
        if (empty($json->locations)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($json->locations as $location) {

            $coordinates = (array) $location->location;

            $results[] = Address::createFromArray([
                'providedBy' => $this->getName(),
                'latitude' => $coordinates['y'],
                'longitude' => $coordinates['x'],
            ]);
        }

        return new AddressCollection($results);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query): Collection
    {
      // There is no unique difference here, just use the existing provider.
      return ArcGISOnline::reverseQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'arcgis_list';
    }

    /**
     * @param string $query
     * @param int    $limit
     *
     * @return string
     */
    private function buildQuery(string $query, int $limit): string
    {
        if (null !== $this->sourceCountry) {
            $query = sprintf('%s&sourceCountry=%s', $query, $this->sourceCountry);
        }

        return sprintf('%s&f=%s', $query, 'json');
    }

    /**
     * @param string $url
     * @param int    $limit
     *
     * @return \stdClass
     */
    private function executeQuery(string $url, int $limit): \stdClass
    {
        $url = $this->buildQuery($url, $limit);
        $content = $this->getUrlContents($url);
        $json = json_decode($content);

        // API error
        if (!isset($json)) {
            throw InvalidServerResponse::create($url);
        }

        return $json;
    }
}
