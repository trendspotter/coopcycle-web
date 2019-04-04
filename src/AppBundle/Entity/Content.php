<?php

namespace AppBundle\Entity;

use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Validator\Constraints as Assert;

class Content
{
    use Timestampable;

    private $id;

    /**
     * @Assert\NotBlank
     * @Assert\Type("string")
     */
    private $key;

    /**
     * @Assert\NotBlank
     * @Assert\Type("string")
     */
    private $markdown;

    public function getId()
    {
        return $this->id;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    public function getMarkdown()
    {
        return $this->markdown;
    }

    public function setMarkdown($markdown)
    {
        $this->markdown = $markdown;

        return $this;
    }
}
