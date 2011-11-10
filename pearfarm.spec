<?php

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
          ->setName('GitPlanbox')
          ->setChannel('ardell.pearfarm.org')
          ->setSummary('A git subcommand for the Planbox project management tool.')
          ->setDescription('A command that allows you to integrate Planbox with the git command line.')
          ->setReleaseVersion('0.0.1')
          ->setReleaseStability('alpha')
          ->setApiVersion('0.0.1')
          ->setApiStability('alpha')
          ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
          ->setNotes('Initial release.')
          ->addMaintainer('lead', 'Jason Ardell', 'ardell', 'ardell@gmail.com')
          ->addGitFiles()
          ;
