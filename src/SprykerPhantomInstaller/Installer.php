<?php

namespace SprykerPhantomInstaller;

use PhantomInstaller\Installer as BaseInstaller;
use Composer\Downloader\TransportException;
use Composer\Composer;

class Installer extends BaseInstaller
{
    /**
     * {@inheritdoc}
     *
     * @param string $targetDir
     * @param string $version
     *
     * @return boolean
     */
    public function download($targetDir, $version)
    {
        if (defined('Composer\Composer::RUNTIME_API_VERSION') && version_compare(Composer::RUNTIME_API_VERSION, '2.0', '>=')) {
            return $this->downloadV2($targetDir, $version);
        }

        return parent::download($targetDir, $version);
    }

    /**
     * @param string $targetDir
     * @param string $version
     *
     * @return bool
     */
    private function downloadV2(string $targetDir, string $version): bool
    {
        $io = $this->getIO();
        $downloadManager = $this->getComposer()->getDownloadManager();
        $retries = count($this->getPhantomJsVersions());
        $composer = $this->getComposer();

        while ($retries--) {
            $package = $this->createComposerInMemoryPackage($targetDir, $version);

            try {
                $loop = $composer->getLoop();

                $promise = $downloadManager->download($package, $targetDir);
                if ($promise) {
                    $loop->wait(array($promise));
                }
                $promise = $downloadManager->prepare('install', $package, $targetDir);
                if ($promise) {
                    $loop->wait(array($promise));
                }
                $promise = $downloadManager->install($package, $targetDir);
                if ($promise) {
                    $loop->wait(array($promise));
                }
                $promise = $downloadManager->cleanup('install', $package, $targetDir);
                if ($promise) {
                    $loop->wait(array($promise));
                }

                return true;

            } catch (TransportException $e) {
                if ($e->getStatusCode() === 404) {
                    $version = $this->getLowerVersion($version);
                    $io->warning('Retrying the download with a lower version number: "' . $version . '"');
                } else {
                    $message = $e->getMessage();
                    $code = $e->getStatusCode();
                    $io->error(PHP_EOL . '<error>TransportException: "' . $message . '". HTTP status code: ' . $code . '</error>');
                    return false;
                }
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $io->error(PHP_EOL . '<error>While downloading version ' . $version . ' the following error accoured: ' . $message . '</error>');
                return false;
            }
        }
    }
}
