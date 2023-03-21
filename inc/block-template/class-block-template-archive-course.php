<?php

/**
 * Class Block_Template_Archive_Course
 *
 * Handle register, render block template
 */
class Block_Template_Archive_Course extends Abstract_Block_Template
{
    public $slug = 'archive-lp_course';
    public $name = 'learnpress/archive-course';
    public $title = 'Course Archive (LearnPress)';
    public $description = 'Course Archive Block Template';
    public $path_html_block_template_file = 'html/archive-lp_course.html';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Render content of block tag
     *
     * @param array $attributes | Attributes of block tag.
     *
     * @return false|string
     */
    public function render_content_block_template(array $attributes)
    {
//      Debug::var_dump($attributes);
        return parent::render_content_block_template($attributes);
    }
}
