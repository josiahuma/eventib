<?php
namespace App\Services;

class SafeEventDescription
{
    public function clean(string $html): string
    {
        if ($html === '') return '';
        if (!class_exists(\DOMDocument::class)) return nl2br(e(strip_tags($html)));
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $body = $document->getElementsByTagName('body')->item(0);
            if (!$body) return '';
            $this->scrub($body);
            $result = '';
            foreach ($body->childNodes as $child) $result .= $document->saveHTML($child);
            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function scrub(\DOMNode $parent): void
    {
        $allowed = ['p','br','strong','b','em','i','u','s','ul','ol','li','blockquote','h2','h3','h4','a','hr'];
        $remove = ['script','style','iframe','object','embed','svg','math','form','input','button','textarea','select','link','meta','base','img','video','audio'];
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof \DOMComment || $node instanceof \DOMProcessingInstruction) { $parent->removeChild($node); continue; }
            if (!$node instanceof \DOMElement) continue;
            $tag = strtolower($node->tagName);
            if (in_array($tag, $remove, true)) { $parent->removeChild($node); continue; }
            $href = $node->getAttribute('href');
            foreach (iterator_to_array($node->attributes) as $attribute) $node->removeAttributeNode($attribute);
            if ($tag === 'a' && preg_match('~^(https?://|mailto:)~i', $href) && !preg_match('/[\x00-\x20]/', $href)) {
                $node->setAttribute('href', $href);
                $node->setAttribute('rel', 'noopener noreferrer');
            }
            $this->scrub($node);
            if (!in_array($tag, $allowed, true)) {
                while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                $parent->removeChild($node);
            }
        }
    }
}
