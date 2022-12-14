<?php

namespace Madmatt\Funnelback;

use SilverStripe\Assets\File;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;

/**
 * Provides ability to integrate with FunnelBack search api
 */
class SearchService
{
    use Configurable;
    use Injectable;

    public const FILE_TYPE_HTML = 'html';

    public function search(string $keyword = "", int $start = 0, int $limit = 10): ?PaginatedList
    {
        // Short circuit - if no keyword is entered, don't bother searching
        if (!$keyword) {
            return PaginatedList::create(ArrayList::create());
        }

        // Fetch results from the gateway and convert them into a standard Silverstripe ArrayList
        try {
            $gateway = SearchGateway::create();
            $data = $gateway->getResults($keyword, $start, $limit);

            if (!$data || !isset($data['results']) || !isset($data['resultsSummary'])) {
                return null;
            }

            $results = $data['results'];
            $list = ArrayList::create();

            foreach ($results as $result) {
                $fileType = $result['fileType'];
                $title = $result['title'];

                // If the file is anything but HTML, then it's downloadable. Ensure we append the file type and file size to the end
                if ($fileType != self::FILE_TYPE_HTML) {
                    $file = $this->getFileFromURL($result['indexUrl']);
                    $title = $this->formatFileTitle(
                        !$file ? 'File Not Found' : $file->Title,
                        $fileType,
                        $result['fileSize']
                    );
                }

                $list->push([
                    'Link' => $result['liveUrl'],
                    'Title' => $title,
                    'Summary' => $result['summary'],
                    'FileType' => $fileType
                ]);
            }

            $list = new PaginatedList($list);
            $list->setPageStart($start);
            $list->setPageLength($limit);
            $list->setTotalItems($data['resultsSummary']['totalMatching']);
            $list->setLimitItems(false);

            return $list;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param string $fileTitle The name of the file as provided by Funnelback (e.g. 'Service Specification')
     * @param string $fileType The file type as provided by Funnelback (e.g. 'pdf')
     * @param string $fileSize The file size  as provided by Funnelback in bytes (e.g. '1048576' for a 1 MB file)
     * @return string
     */
    protected function formatFileTitle(string $fileTitle, string $fileType, string $fileSize): string
    {
        return sprintf(
            "%s (%s %s)",
            trim($fileTitle),
            strtoupper($fileType),
            $this->formatFileSizeString($fileSize)
        );
    }

    /**
     * Format the filesize in bytes to the nearest highest unit (e.g. show the size in MB until we to a file that is
     * greater than 1GB in size).
     *
     * @param string $fileSizeBytes The file size in bytes as a integer string (e.g. '1048576')
     * @return string
     */
    protected function formatFileSizeString(string $fileSizeBytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $prefixIndex = floor(($fileSizeBytes ? log($fileSizeBytes) : 0) / log(1024));
        $prefixIndex = min($prefixIndex, count($units) - 1);

        $fileSizeBytes /= pow(1024, $prefixIndex);

        return round($fileSizeBytes, 0) . $units[$prefixIndex];
    }

    /**
     * The functions takes in a full url (including protocol) and tries to find a
     * matching file in the assets folder
     *
     *
     * @param string $url
     * @return File|null
     */
    private function getFileFromURL(string $url): ?File
    {
        $parts =   array_filter(preg_split("#[/\\\\]+#", $url ?? '') ?? []);
        unset($parts[0]);
        unset($parts[1]);
        unset($parts[2]);
        $filePath = implode('/', $parts);

        return File::find($filePath);
    }
}
