<?php

namespace Pickleball\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

class ServeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('pickleball:serve')
            ->setDescription('Simulate games to determine server win percentages')
            ->addOption(
                'debug',
                'd',
                InputOption::VALUE_NONE,
                'If set, print debug messages'
            )
            ->addOption(
                'points',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Number of points to win the game',
                11
            )
            ->addOption(
                'winPct',
                'w',
                InputOption::VALUE_OPTIONAL,
                'Per-point win rate of first team against second team',
                50
            )
            ->addOption(
                'defPct',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Percentage point increase of per-point win rate when playing defense',
                0
            )
            ->addOption(
                'sims',
                's',
                InputOption::VALUE_OPTIONAL,
                'Number of simulations to run',
                100000
            )
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->in = $input;
        $this->out = $output;
        $this->out->setFormatter(new OutputFormatter(true));
        $this->debug = (boolean) $input->getOption('debug');
        $this->points = (integer) $input->getOption('points');
        $this->winPct = (integer) $input->getOption('winPct');
        $this->defPct = (integer) $input->getOption('defPct');
        $this->sims = (integer) $input->getOption('sims');
        
        // calculate chance for receiving teams to win a point
        $receiverPcts = [
            0 => $this->winPct + $this->defPct,
            1 => (100 - $this->winPct) + $this->defPct
        ];
        // calculate chance for serving teams to win to a point
        $this->serverPcts = [
            0 => 100 - $receiverPcts[1],
            1 => 100 - $receiverPcts[0],
        ];
        
        $this->out->writeln('');
        $this->out->writeln("<info>Games are played to {$this->points}</info>");
        $this->out->writeln("<info>First team wins {$this->serverPcts[0]}% of points while serving; {$receiverPcts[0]}% while receiving</info>");
        $this->out->writeln("<info>Second team wins {$this->serverPcts[1]}% of points while serving; {$receiverPcts[1]}% while receiving</info>");
        $this->out->writeln("<info>Simulating {$this->sims} games...</info>");
        
        // run the simulations
        $wins = [0, 0];
        for ($i = 1; $i <= $this->sims; $i++) {
            $wins[$this->play()]++;
            if ($i % 500000 === 0) {
                $this->out->writeln("... [" . date('Ymd H:i:s'). "] $i simulations complete...");
            }
        }
        
        $this->out->writeln('');
        $this->out->writeln("<comment>First team win pct: " . (round(100 * $wins[0] / $this->sims, 2)) . '%</comment>');
        $this->out->writeln("<comment>Second team win pct: " . (round(100 * $wins[1] / $this->sims, 2)) . '%</comment>');
    }
    
    private function isGameOver($firstTeamPts, $secondTeamPts)
    {
        return $firstTeamPts >= $this->points || $secondTeamPts >= $this->points;
    }
    
    private function play()
    {
        $pts = [0, 0];
        $servingTeamIdx = 0;
        $firstServe = true;
        
        while (!$this->isGameOver($pts[0], $pts[1])) {
            $servers = $firstServe ? 1 : 2;
            
            for ($i = 0; $i < $servers; $i++) {
                while ($this->serverPcts[$servingTeamIdx] >= rand(1, 100) && !$this->isGameOver($pts[0], $pts[1])) {
                    $pts[$servingTeamIdx]++;
                }
            }
                        
            // switch team
            $servingTeamIdx = (integer) !$servingTeamIdx;
            $firstServe = false;
        }
        
        if ($this->debug) {
            $this->out->writeln("GAME: {$pts[0]} - {$pts[1]}");
        }
        
        return $pts[0] > $pts[1] ? 0 : 1;
    }
}
