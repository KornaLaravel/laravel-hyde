<?php

namespace App\Hyde;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\NoReturn;
use JetBrains\PhpStorm\Pure;

/**
 * Parses a Markdown file into an object with support for Front Matter.
 *
 * Note that it does not convert it to HTML.
 */
class MarkdownPostParser
{
    /**
     * @var string the full path to the Markdown file
     */
    private string $filepath;

    /**
     * The extracted Front Matter
     * @var array
     */
    public array $matter;

    /**
     * The extracted Markdown body
     * @var string
     */
    public string $body;

    /**
     * @param string $slug of the Markdown file (without extension)
     * @throws Exception if the file cannot be found in _posts
     * @example `new MarkdownPostParser('example-post')`
     */
    public function __construct(protected string $slug)
    {
        $this->filepath = realpath('./_posts') . "/$slug.md";
        if (!file_exists($this->filepath)) {
            throw new Exception("File _posts/$slug.md not found.", 404);
        }

        $this->execute();
    }

    /**
     * Handle the parsing job.
     * @return void
     */
    #[NoReturn]
    public function execute(): void
    {
        // Get the text stream from the markdown file
        $stream = file_get_contents($this->filepath);

        // Split out the front matter and markdown
        $split = $this->split($stream);

        $this->matter = array_merge($this->parseFrontMatter($split['matter']), [
            'slug' => $this->slug // Make sure to use the filename as the slug and not any potential override
        ]);

        // Implode the line array back into a markdown string
        $this->body = implode("\n", $split['markdown']);
    }

    /**
     * Split the front matter from the markdown.
     * @param string $stream
     * @return array
     */
    #[ArrayShape(['matter' => "array", 'markdown' => "array"])]
    public function split(string $stream): array
    {
        $lines = explode("\n", $stream);

        // Find the start and end position of the YAML block.
        // Note that unless something is wrong with the file the start index should always be 0.
        $matterSectionIndex = [];
        foreach ($lines as $lineNumber => $lineContents) {
            if (str_starts_with($lineContents, '---')) {
                if (sizeof($matterSectionIndex) === 0) {
                    $matterSectionIndex['start'] = $lineNumber;
                } else if (sizeof($matterSectionIndex) === 1) {
                    $matterSectionIndex['end'] = $lineNumber;
                    break;
                }
            }
        }

        // Construct the new line arrays
        $matter = []; $markdown = [];
        foreach ($lines as $lineNumber => $lineContents) {
            if ($lineNumber <= $matterSectionIndex['end']) {
                $matter[] = $lineContents;
            } else {
                $markdown[] = $lineContents;
            }
        }

        // Remove the dashes
        unset($matter[$matterSectionIndex['start']]);
        unset($matter[$matterSectionIndex['end']]);

        return [
            'matter' => $matter,
            'markdown' => $markdown,
        ];
    }

    /**
     * Parse lines of Front Matter YAML into an associative array.
     * @param array $lines
     * @return array
     */
    public function parseFrontMatter(array $lines): array
    {
        $matter = [];
        foreach ($lines as $line) {
            $array = (explode(': ', $line, 2));
            $matter[$array[0]] = $array[1];
        }
        return $matter;
    }

    /**
     * Get the Markdown Post Object.
     * @return MarkdownPost
     */
    #[Pure]
    public function get(): MarkdownPost
    {
        return new MarkdownPost($this->matter, $this->body, $this->slug);
    }
}