<?php

class Charlies extends LunchMenuSource
{
	public $icon = 'charlies';

	public function __construct($title, $link) {
		$this->title = $title;
		$this->link = $link;
		$this->sourceLink = "$link/menu";
	}

	public function getTodaysMenu($todayDate, $cacheSourceExpires)
	{
		$cached = $this->downloadHtml($cacheSourceExpires);
		$result = new LunchMenuResult($cached['stored']);

		$today = get_czech_day(date('w', $todayDate));

		$content = $cached['html']->find("div.entry-content", 0);
		$tables = $content? $content->find("table.menu-one-day") : array();

		if ($tables) {
			foreach ($tables as $table) {
				$title = $this->normalizeText($table->find("th.table-title", 0)->plaintext);
				$this->processMenuBlock($result, $today, $title, $table, true);
			}

			return $result;
		}

		$menus = $cached['html']->find("div.menu-one-day");
		if (!$menus) {
			throw new ScrapingFailedException("menu-one-day not found");
		}

		foreach ($menus as $menu) {
			$title = '';
			$parent = $menu->parent();
			$titleElement = $parent? $parent->find("h2", 0) : null;
			if ($titleElement) {
				$title = $this->normalizeText($titleElement->plaintext);
			}
			$this->processMenuBlock($result, $today, $title, $menu, false);
		}

		return $result;
	}

	protected function processMenuBlock(&$result, $today, $title, $block, $isTable)
	{
		$title_lc = mb_strtolower($title);
		if ($title_lc == 'nabídka baru') {
			return;
		}

		if (strpos($title_lc, $today) !== FALSE) {
			// todays menu
			$this->processDishes($result, null, $block, $isTable);

		} else {
			$isOtherDay = false;
			foreach(get_all_czech_days() as $day) {
				if (strpos($title_lc, $day) !== FALSE) {
					$isOtherDay = true;
					break;
				}
			}

			if (!$isOtherDay) {
				$this->processDishes($result, $title, $block, $isTable);
			}
		}
	}

	protected function processDishes(&$result, $group, $block, $isTable)
	{
		if ($isTable) {
			$this->processTable($result, $group, $block);
		} else {
			$this->processDivMenu($result, $group, $block);
		}
	}

	protected function processTable(&$result, $group, $table)
	{
		foreach ($table->find('tr') as $tr) {
			if (!$tr->find('td')) {
				continue;
			}

			$what = $this->normalizeText($tr->find('td', 0)->plaintext);
			$price = $this->normalizeText($tr->find('td', 1)->plaintext);

			$result->dishes[] = new Dish($what, $price, NULL, $group);
		}
	}

	protected function processDivMenu(&$result, $group, $menu)
	{
		foreach ($menu->children() as $row) {
			if ($row->tag != 'div') {
				continue;
			}

			$columns = array();
			foreach ($row->children() as $column) {
				if ($column->tag == 'div') {
					$columns[] = $this->normalizeText($column->plaintext);
				}
			}

			if (count($columns) < 2) {
				continue;
			}

			$label = count($columns) > 2? array_shift($columns) : '';
			$price = array_pop($columns);
			$what = implode(' ', $columns);
			if ($what == '' || $price == '') {
				continue;
			}

			if (preg_match('/^Menu\s+\d+$/ui', $label)) {
				$what = "$label: $what";
			}

			$result->dishes[] = new Dish($what, $price, NULL, $group);
		}
	}

	protected function normalizeText($text)
	{
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/\s+/u', ' ', $text);
		return trim($text);
	}
}
