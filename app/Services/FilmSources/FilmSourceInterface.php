<?php

namespace App\Services\FilmSources;

interface FilmSourceInterface
{
    /**
     * Return pagination info: total pages, total films, perPage, currentPage
     * @return array
     */
    public function getPagination(): array;

    /**
     * Return list of slugs on given page
     * @param int $page
     * @return array
     */
    public function getItemsByPage(int $page): array;

    /**
     * Return detail payload for a given slug
     * @param string $slug
     * @return array
     */
    public function getDetail(string $slug): array;

}
