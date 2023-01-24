<?php

namespace Madmatt\Funnelback;

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

    public function search(string $keyword = "", int $start = 0, int $limit = 10, string $sort = ""): ?PaginatedList
    {
        // Short circuit - if no keyword is entered, don't bother searching
        if (!$keyword) {
            return PaginatedList::create(ArrayList::create());
        }

        // Fetch results from the gateway and convert them into a standard Silverstripe ArrayList
        try {
            $gateway = SearchGateway::create();
            $data = $gateway->getResults($keyword, $start, $limit, $sort);

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
                    $title = $this->formatFileTitle($title, $fileType, $result['fileSize']);
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
}
