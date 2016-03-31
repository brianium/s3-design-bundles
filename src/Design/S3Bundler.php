<?php

namespace ProPhoto\S3DesignBundles\Design;

use Aws\S3\S3Client;
use ProPhoto\Core\Model\Design\Bundle;
use ProPhoto\Infrastructure\Service\Design\Distribution\Bundler;

class S3Bundler extends Bundler
{
    /**
     * Export the bundle to a path and return it
     *
     * @param Bundle $bundle
     * @return string
     */
    public function bundle(Bundle $bundle)
    {
        // return null if json file could not be created
        $jsonFile = $this->bundleData($bundle);
        if ($jsonFile === null) {
            return null;
        }

        // return null if ANY assets failed to upload to S3
        try {
            $this->upload($bundle);
        } catch(\Exception $e) {
            return null;
        }

        // create a zip out of just the json file
        $fileName = $this->getPath($bundle, 'zip');
        $zip = new \PclZip($fileName);
        $result = $zip->create([$jsonFile], PCLZIP_OPT_REMOVE_ALL_PATH);

        // return null if compression fails
        if ($result === 0) {
            return null;
        }

        return $fileName;
    }

    /**
     * Upload all assets to S3
     *
     * @param Bundle $bundle
     */
    protected function upload(Bundle $bundle)
    {
        $config = $this->getAwsConfig();
        $client = new S3Client($config);
        $bucket = get_option('prophoto_s3_bundler_bucket');
        $this->uploadAssets($client, $bundle, $bucket);
        $this->uploadGalleries($client, $bundle, $bucket);
    }

    /**
     * Upload all images from the bundle to S3
     *
     * @param S3Client $client
     * @param Bundle $bundle
     * @param string $bucket
     */
    protected function uploadAssets(S3Client $client, Bundle $bundle, $bucket)
    {
        $dir = sanitize_title_with_dashes($bundle->getName());
        $images = $bundle->getImages();
        $fonts = $bundle->getFonts();
        $assets = array_merge($images, $fonts);
        foreach ($assets as $asset) {
            $client->putObject([
                'Bucket' => $bucket,
                'Key' => "$dir/" . basename($asset),
                'Body' => fopen($asset, 'r'),
                'ACL' => 'public-read'
            ]);
        }
    }

    /**
     * Upload galleries from the bundle to S3
     *
     * @param S3Client $client
     * @param Bundle $bundle
     * @param $bucket
     */
    protected function uploadGalleries(S3Client $client, Bundle $bundle, $bucket)
    {
        $galleries = $bundle->getGalleries();
        if (empty($galleries)) {
            return;
        }

        $dir = sanitize_title_with_dashes($bundle->getName());
        foreach ($galleries as $gallery) {
            $id = $gallery->getId();
            $folder = "$dir/$id";
            $images = $gallery->getImages();
            foreach ($images as $image) {
                $client->putObject([
                    'Bucket' => $bucket,
                    'Key' => "$folder/" . basename($image),
                    'Body' => fopen($image, 'r'),
                    'ACL' => 'public-read'
                ]);
            }
        }
    }

    /**
     * Get config used for communicating with S3
     *
     * @return array
     */
    protected function getAwsConfig()
    {
        return [
            'version' => 'latest',
            'region' => get_option('prophoto_s3_bundler_region'),
            'credentials' => [
                'key' => get_option('prophoto_s3_bundler_key'),
                'secret' => get_option('prophoto_s3_bundler_secret'),
            ]
        ];
    }
}