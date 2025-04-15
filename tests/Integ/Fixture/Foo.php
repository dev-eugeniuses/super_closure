<?php namespace SuperClosure\Test\Integ\Fixture;

class Foo
{
    private mixed $bar;

    public function __construct($bar = null)
    {
        $this->bar = $bar;
    }

    public function getClosure(): callable
    {
        return function () {
            return $this->bar;
        };
    }
}
