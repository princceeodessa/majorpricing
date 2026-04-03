<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\File;
use ZipArchive;

class CatalogWorkbookImageService
{
    /**
     * @return array<string, array<int, string>>
     */
    public function extractSheetImageMap(string $xlsxPath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($xlsxPath) !== true) {
            return [];
        }

        File::deleteDirectory(public_path('catalog-media'));
        File::ensureDirectoryExists(public_path('catalog-media'));

        $sheetImageMap = [];
        $worksheetTargets = $this->parseWorksheetTargets($zip);

        foreach ($worksheetTargets as $sheetName => $worksheetPath) {
            foreach ($this->resolveWorksheetDrawingPaths($zip, $worksheetPath) as $drawingPath) {
                $drawingRelationships = $this->parseRelationshipTargets($zip, $this->makeRelsPath($drawingPath), $drawingPath);

                foreach ($this->parseDrawingRows($zip, $drawingPath, $drawingRelationships) as $rowNumber => $mediaPath) {
                    $relativePath = $this->extractMediaFile($zip, $mediaPath);

                    if ($relativePath !== null) {
                        $sheetImageMap[$sheetName][$rowNumber] ??= $relativePath;
                    }
                }
            }
        }

        $zip->close();

        return $sheetImageMap;
    }

    /**
     * @return array<string, string>
     */
    private function parseWorksheetTargets(ZipArchive $zip): array
    {
        $workbookXml = $this->readZipEntry($zip, 'xl/workbook.xml');
        $workbookRelsXml = $this->readZipEntry($zip, 'xl/_rels/workbook.xml.rels');

        if ($workbookXml === null || $workbookRelsXml === null) {
            return [];
        }

        $sheetTargetsByRelation = $this->parseRelationshipTargets($zip, 'xl/_rels/workbook.xml.rels', 'xl/workbook.xml');
        $document = $this->loadDocument($workbookXml);

        if ($document === null) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $targets = [];

        foreach ($xpath->query('//main:sheets/main:sheet') ?: [] as $sheetNode) {
            if (! $sheetNode instanceof DOMElement) {
                continue;
            }

            $sheetName = $sheetNode->getAttribute('name');
            $relationId = $sheetNode->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');

            if ($sheetName === '' || $relationId === '' || ! isset($sheetTargetsByRelation[$relationId])) {
                continue;
            }

            $targets[$sheetName] = $sheetTargetsByRelation[$relationId];
        }

        return $targets;
    }

    /**
     * @return array<int, string>
     */
    private function resolveWorksheetDrawingPaths(ZipArchive $zip, string $worksheetPath): array
    {
        $relsPath = $this->makeRelsPath($worksheetPath);

        if ($this->readZipEntry($zip, $relsPath) === null) {
            return [];
        }

        return array_values(array_filter(
            $this->parseRelationshipTargets($zip, $relsPath, $worksheetPath),
            static fn (string $path): bool => str_contains($path, '/drawings/'),
        ));
    }

    /**
     * @param  array<string, string>  $drawingRelationships
     * @return array<int, string>
     */
    private function parseDrawingRows(ZipArchive $zip, string $drawingPath, array $drawingRelationships): array
    {
        $drawingXml = $this->readZipEntry($zip, $drawingPath);

        if ($drawingXml === null) {
            return [];
        }

        $document = $this->loadDocument($drawingXml);

        if ($document === null) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $rows = [];

        foreach ($xpath->query('//xdr:oneCellAnchor | //xdr:twoCellAnchor') ?: [] as $anchorNode) {
            if (! $anchorNode instanceof DOMElement) {
                continue;
            }

            $rowNode = $xpath->query('./xdr:from/xdr:row', $anchorNode)?->item(0);
            $blipNode = $xpath->query('.//a:blip', $anchorNode)?->item(0);

            if (! $rowNode instanceof DOMElement || ! $blipNode instanceof DOMElement) {
                continue;
            }

            $rowNumber = ((int) trim($rowNode->textContent)) + 1;
            $embedId = $blipNode->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');

            if ($rowNumber < 1 || $embedId === '' || ! isset($drawingRelationships[$embedId])) {
                continue;
            }

            $rows[$rowNumber] ??= $drawingRelationships[$embedId];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function parseRelationshipTargets(ZipArchive $zip, string $relsPath, string $basePath): array
    {
        $xml = $this->readZipEntry($zip, $relsPath);

        if ($xml === null) {
            return [];
        }

        $document = $this->loadDocument($xml);

        if ($document === null) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('rels', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $targets = [];

        foreach ($xpath->query('//rels:Relationship') ?: [] as $relationshipNode) {
            if (! $relationshipNode instanceof DOMElement) {
                continue;
            }

            $id = $relationshipNode->getAttribute('Id');
            $target = $relationshipNode->getAttribute('Target');

            if ($id === '' || $target === '') {
                continue;
            }

            $targets[$id] = $this->resolveZipPath($basePath, $target);
        }

        return $targets;
    }

    private function extractMediaFile(ZipArchive $zip, string $mediaPath): ?string
    {
        $entryStream = $zip->getStream($mediaPath);

        if ($entryStream === false) {
            return null;
        }

        $fileName = basename($mediaPath);
        $relativePath = 'catalog-media/'.$fileName;
        $destination = public_path($relativePath);

        if (! File::exists($destination)) {
            File::ensureDirectoryExists(dirname($destination));

            $destinationStream = fopen($destination, 'wb');

            if ($destinationStream === false) {
                fclose($entryStream);

                return null;
            }

            stream_copy_to_stream($entryStream, $destinationStream);
            fclose($destinationStream);
        }

        fclose($entryStream);

        return $relativePath;
    }

    private function makeRelsPath(string $filePath): string
    {
        $directory = str_replace('\\', '/', dirname($filePath));
        $baseName = basename($filePath);

        return $directory.'/_rels/'.$baseName.'.rels';
    }

    private function resolveZipPath(string $basePath, string $target): string
    {
        $target = str_replace('\\', '/', $target);
        $target = ltrim($target, '/');

        if (str_starts_with($target, 'xl/')) {
            return $target;
        }

        $baseDirectory = str_replace('\\', '/', dirname($basePath));
        $parts = array_filter(explode('/', $baseDirectory.'/'.$target), static fn (string $part): bool => $part !== '' && $part !== '.');
        $resolved = [];

        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($resolved);

                continue;
            }

            $resolved[] = $part;
        }

        return implode('/', $resolved);
    }

    private function readZipEntry(ZipArchive $zip, string $path): ?string
    {
        $content = $zip->getFromName($path);

        return $content === false ? null : $content;
    }

    private function loadDocument(string $xml): ?DOMDocument
    {
        $document = new DOMDocument();

        return @$document->loadXML($xml) ? $document : null;
    }
}
