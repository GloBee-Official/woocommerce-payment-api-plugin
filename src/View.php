<?php

namespace GloBee\WooCommerce;

class View
{

    protected $path = '';

    protected $obLevel = 0;

    public static function make($path, $data)
    {
        $view = new self;

        return $view->generate($path, $data);
    }

    /**
     * Get the evaluated contents of the view at the given path.
     *
     * @param  string $__path
     * @param  array $__data
     *
     * @return string
     * @throws \Exception
     */
    protected function generate($__path, $__data)
    {
        $this->path = __DIR__.'/views/'.$__path.'.php';
        extract($__data);

        $this->obLevel = ob_get_level();
        ob_start();

        try {
            include $this->path;
        } catch (\Exception $e) {
            while (ob_get_level() > $this->obLevel) {
                ob_end_clean();
            }

            throw $e;
        }

        return ltrim(ob_get_clean());
    }
}
