<?php

declare(strict_types=1);

namespace MetricPoster;


class AppModel
{
	private $app_name;
	private $app_id;
	private $nr_id;
	private $nr_browser_guid;
	private $nr_app_guid;
	private $template_file;
	
	public function __construct($app_name, $app_id, $nr_id, $nr_browser_guid, $nr_app_guid, $template_file)
	{
		$this->app_name = $app_name;
		$this->app_id = $app_id;
		$this->nr_id = $nr_id;
		$this->nr_browser_guid = $nr_browser_guid;
		$this->nr_app_guid = $nr_app_guid;
		$this->template_file = empty($template_file) ? 'post.tpl.html' : $template_file;
	}

	public function get_app_name(): string
	{
		return $this->app_name;
	}

	public function get_app_id(): string
	{
		return $this->app_id;
	}

	public function get_nr_id(): string
	{
		return $this->nr_id;
	}

	public function get_nr_browser_guid(): string
	{
		return $this->nr_browser_guid;
	}

	public function get_nr_app_guid(): string
	{
		return $this->nr_app_guid;
	}

	public function set_app_name(string $app_name): void
	{
		$this->app_name = $app_name;
	}

	public function set_app_id(string $app_id): void
	{
		$this->app_id = $app_id;
	}

	public function set_nr_id(string $nr_id): void
	{
		$this->nr_id = $nr_id;
	}

	public function set_nr_browser_guid(string $nr_browser_guid): void
	{
		$this->nr_browser_guid = $nr_browser_guid;
	}

	public function set_nr_app_guid(string $nr_app_guid): void
	{
		$this->nr_app_guid = $nr_app_guid;
	}

	public function get_template_file(): string
	{
		return $this->template_file;
	}

	public function set_template_file(string $template_file): void
	{
		$this->template_file = $template_file;
	}
}
