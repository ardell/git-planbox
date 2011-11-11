# git-planbox

## Description

A git subcommand for the Planbox project management tool.

## Installation

1. Clone the repo
2. Step into git-planbox
3. Clone the CLI repo git://github.com/apinstein/climax.git
4. Add dir git-planbox to your path
5. Edit ~/.gitconfig and add a Planbox section:
[planbox]
   email=<your email used to log into planbox>
   password=<your planbox password
   productid=<planbox product id>
6. Run `git planbox <subcommand>`

## Sub-commands

### git-planbox help

Generate a help message.

### git-planbox list

List stories.

### git-planbox show [&lt;storyid&gt;]

Show name, status, description, tasks, and comments for the specified story. If no storyid is specified then git-planbox will attempt to parse a storyid from the current git branch name.

### git-planbox start &lt;storyid&gt;

Start the specified story. Creates a new branch according to the naming template defined in git config.

## License

MIT. Pull requests gladly accepted.
