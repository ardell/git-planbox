<?php

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
          ->setName('GitPlanbox')
          ->setChannel('ardell.pearfarm.org')
          ->setSummary('A git subcommand for the Planbox project management tool.')
          ->setDescription('A command that allows you to integrate Planbox with the git command line.')
          ->setReleaseStability('alpha')
          ->setReleaseVersion('0.0.15')
          ->setApiVersion('0.0.15')
          ->setApiStability('alpha')
          ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
          ->setNotes('Allow listing stories for iterations other than current.')
          ->addMaintainer('lead', 'Jason Ardell', 'ardell', 'ardell@gmail.com')
          ->addGitFiles()
          ->addExecutable('git-planbox')
          ->addPackageDependency('climax', 'apinstein.pearfarm.org')
          ;
