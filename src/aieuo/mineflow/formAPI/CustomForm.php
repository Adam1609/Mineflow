<?php

namespace aieuo\mineflow\formAPI;

use aieuo\mineflow\formAPI\element\Dropdown;
use aieuo\mineflow\formAPI\element\Element;
use aieuo\mineflow\formAPI\element\Input;
use aieuo\mineflow\formAPI\element\Slider;
use aieuo\mineflow\formAPI\element\Toggle;
use aieuo\mineflow\formAPI\response\CustomFormResponse;
use aieuo\mineflow\utils\Language;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class CustomForm extends Form {

    protected $type = self::CUSTOM_FORM;

    /** @var Element[] */
    private $contents = [];

    /**
     * @param array $contents
     * @return self
     */
    public function setContents(array $contents): self {
        $this->contents = $contents;
        return $this;
    }

    /**
     * @param Element $content
     * @param bool $add
     * @return self
     */
    public function addContent(Element $content, bool $add = true): self {
        if ($add) $this->contents[] = $content;
        return $this;
    }

    /**
     * @return Element[]
     */
    public function getContents(): array {
        return $this->contents;
    }

    public function getContent(int $index): ?Element {
        return $this->contents[$index] ?? null;
    }

    public function addContents(Element ...$contents): self {
        $this->contents = array_merge($this->contents, $contents);
        return $this;
    }

    public function setContent(Element $element, int $index): self {
        $this->contents[$index] = $element;
        return $this;
    }

    public function removeContentAt(int $index): self {
        unset($this->contents[$index]);
        $this->contents = array_values($this->contents);
        return $this;
    }

    public function jsonSerialize(): array {
        $form = [
            "type" => "custom_form",
            "title" => Language::replace($this->title),
            "content" => $this->contents
        ];
        $form = $this->reflectErrors($form);
        return $form;
    }

    public function resetErrors(): Form {
        foreach ($this->getContents() as $content) {
            $content->setHighlight(null);
            $content->setExtraText("");
        }
        return parent::resetErrors();
    }

    public function reflectErrors(array $form): array {
        for ($i = 0, $iMax = count($form["content"]); $i < $iMax; $i++) {
            if (empty($this->highlights[$i])) continue;
            /** @var Element $content */
            $content = $form["content"][$i];
            $content->setHighlight(TextFormat::YELLOW);
        }
        if (!empty($this->messages) and !empty($this->contents)) {
            $form["content"][0]->setExtraText(implode("\n", array_keys($this->messages))."\n");
        }
        return $form;
    }

    public function resend(array $errors = [], array $messages = [], array $responseOverrides = [], array $elementOverrides = []): void {
        if (empty($this->lastResponse) or !($this->lastResponse[0] instanceof Player) or !$this->lastResponse[0]->isOnline()) return;

        foreach ($elementOverrides as $i => $element) {
            $this->setContent($element, $i);
        }
        $this->setDefaultsFromResponse($this->lastResponse[1], $responseOverrides)
            ->resetErrors()
            ->addMessages($messages)
            ->addErrors($errors)
            ->show($this->lastResponse[0]);
    }

    public function handleResponse(Player $player, $data): void {
        $this->lastResponse = [$player, $data];
        if ($data !== null) {
            $response = new CustomFormResponse($this, $data);
            foreach ($this->getContents() as $i => $content) {
                $response->setCurrentIndex($i);
                $content->onFormSubmit($response, $player);
            }

            $callback = $response->getInterruptCallback();
            if (is_callable($callback) and $callback($response->isResponseIgnored())) return;

            if (!$response->isResponseIgnored()) {
                if ($response->shouldResendForm() or $response->hasError()) {
                    $this->resend($response->getErrors(), [], $response->getDefaultOverrides(), $response->getElementOverrides());
                    return;
                }
            }

            foreach ($response->getResponseOverrides() as $i => $override) {
                $data[$i] = $override;
            }
        }

        parent::handleResponse($player, $data);
    }

    private function setDefaultsFromResponse(array $data, array $overwrites): self {
        foreach ($this->getContents() as $i => $content) {
            if ($content instanceof Input or $content instanceof Slider or $content instanceof Dropdown or $content instanceof Toggle) {
                $content->setDefault($overwrites[$i] ?? $data[$i]);
            }
        }
        return $this;
    }

    public function __clone() {
        $elements = [];
        foreach ($this->getContents() as $i => $content) {
            $elements[$i] = clone $content;
        }
        $this->setContents($elements);
    }
}