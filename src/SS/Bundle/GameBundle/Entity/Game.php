<?php

namespace SS\Bundle\GameBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Game
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="SS\Bundle\GameBundle\Entity\GameRepository")
 */
class Game
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var array
     *
     * @ORM\Column(name="type", type="string", columnDefinition="ENUM('Poker', 'Solitaire', 'Hearts', 'Spades')")
     */
    private $type;

    /**
     * @var integer
     *
     * @ORM\Column(name="num_players", type="integer")
     */
    private $numPlayers = 0;

    /**
     * @var integer
     *
     * @ORM\Column(name="is_active", type="smallint")
     */
    private $isActive = 1;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="started_on", type="datetime")
     */
    private $startedOn;

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set type
     *
     * @param array $type
     * @return Game
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return array 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set numPlayers
     *
     * @param integer $numPlayers
     * @return Game
     */
    public function setNumPlayers($numPlayers)
    {
        $this->numPlayers = $numPlayers;

        return $this;
    }

    /**
     * Get numPlayers
     *
     * @return integer 
     */
    public function getNumPlayers()
    {
        return $this->numPlayers;
    }

    /**
     * Set isActive
     *
     * @param integer $isActive
     * @return Game
     */
    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * Get isActive
     *
     * @return integer 
     */
    public function getIsActive()
    {
        return $this->isActive;
    }

    /**
     * Set startedOn
     *
     * @param \DateTime $startedOn
     * @return Game
     */
    public function setStartedOn($startedOn)
    {
        $this->startedOn = $startedOn;

        return $this;
    }

    /**
     * Get startedOn
     *
     * @return \DateTime 
     */
    public function getStartedOn()
    {
        return $this->startedOn;
    }
}
