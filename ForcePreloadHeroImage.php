<?php
/**
 * Force preloading the hero images.
 *
 * @package Google\AmpHeroImagePreloading
 */

namespace Google\AmpForceHeroImagePreloading;

use AmpProject\Dom\Document;
use AmpProject\Optimizer\ErrorCollection;
use AmpProject\Optimizer\Transformer;
use AmpProject\Attribute;
use AmpProject\Tag;
use AmpProject\Dom\Element;
use AmpProject\RequestDestination;
use AmpProject\Extension;
use AmpProject\Url;
use AmpProject\Optimizer\HeroImage;

/**
 * Transformer which rewrites image URLs to point the AMP Cache as a CDN.
 */
final class ForcePreloadHeroImage implements Transformer {

	/**
	 * Reference node to attach preload links to.
	 *
	 * @var Element|null
	 */
	private $preload_reference_node;

	/**
	 * Apply transformations to the provided DOM document.
	 *
	 * @param Document        $document DOM document to apply the transformations to.
	 * @param ErrorCollection $errors   Collection of errors that are collected during transformation.
	 */
	public function transform( Document $document, ErrorCollection $errors ) {
		$elements = $document->xpath->query(
			'.//amp-img[ @data-hero and @i-amphtml-ssr ][ not( img/@loading ) or "lazy" != img/@loading ]',
			$document->body
		);

		foreach ( $elements as $element ) {
			/** @var Element $element */
			$src = $element->getAttribute( Attribute::SRC );
			if ( Extension::IMG === $element->tagName && ( new Url( $src ) )->isValidNonDataUrl() ) {
				$hero_image = new HeroImage(
					$src,
					$element->getAttribute( Attribute::MEDIA ),
					$element->getAttribute( Attribute::SRCSET ),
					$element
				);
				$this->generatePreload( $hero_image, $document );
			}
		}
	}

	/**
	 * Generate the preload link for a given hero image.
	 *
	 * This is adapted from the same method in the PreloadHeroImage transformer in amp-toolbox-php.
	 *
	 * @see Transformer\PreloadHeroImage::generatePreload()
	 * @link https://github.com/ampproject/amp-toolbox-php/blob/86d53aa73edef1aafd748fb94646af6859414e2a/src/Optimizer/Transformer/PreloadHeroImage.php#L499-L552
	 *
	 * @param HeroImage $hero_image Hero image to generate the preload link for.
	 * @param Document  $document   Document to generate the preload link in.
	 */
	private function generatePreload( HeroImage $hero_image, Document $document ) {
		if ( $this->hasExistingImagePreload( $document, $hero_image->getSrc() ) ) {
			return;
		}

		if ( ! $this->preload_reference_node ) {
			$this->preload_reference_node = $document->viewport;
		}

		$preload = $document->createElement( Tag::LINK );
		$preload->setAttribute( Attribute::REL, Attribute::REL_PRELOAD );
		$preload->setAttribute( Attribute::HREF, $hero_image->getSrc() );
		$preload->setAttribute( Attribute::AS_, RequestDestination::IMAGE );
		$preload->appendChild( $document->createAttribute( Attribute::DATA_HERO ) );
		if ( $hero_image->getSrcset() ) {
			$preload->setAttribute( Attribute::IMAGESRCSET, $hero_image->getSrcset() );
			$img = $hero_image->getAmpImg();
			if ( $img && $img->hasAttribute( Attribute::SIZES ) ) {
				$preload->setAttribute( Attribute::IMAGESIZES, $img->getAttribute( Attribute::SIZES ) );
			}
		}

		$media = $hero_image->getMedia();
		if ( $media ) {
			$preload->setAttribute( Attribute::MEDIA, $hero_image->getMedia() );
		}

		if ( $this->preload_reference_node ) {
			$this->preload_reference_node->parentNode->insertBefore(
				$preload,
				$this->preload_reference_node->nextSibling
			);
		} else {
			$document->head->appendChild( $preload );
		}

		$this->preload_reference_node = $preload;
	}

	/**
	 * Check whether an existing preload link exists for a given src.
	 *
	 * This is adapted from the same method in the PreloadHeroImage transformer in amp-toolbox-php.
	 *
	 * @see Transformer\PreloadHeroImage::hasExistingImagePreload()
	 * @link https://github.com/ampproject/amp-toolbox-php/blob/86d53aa73edef1aafd748fb94646af6859414e2a/src/Optimizer/Transformer/PreloadHeroImage.php#L611-L639
	 *
	 * @param Document $document Document in which to check for an existing preload.
	 * @param string   $src      Preload URL to look for.
	 *
	 * @return bool Whether an existing preload already exists.
	 */
	private function hasExistingImagePreload( Document $document, $src ) {
		foreach ( $document->head->childNodes as $node ) {
			if ( ! $node instanceof Element ) {
				continue;
			}

			if ( $node->getAttribute( Attribute::REL ) !== Attribute::REL_PRELOAD ) {
				continue;
			}

			if ( $node->getAttribute( Attribute::AS_ ) !== RequestDestination::IMAGE ) {
				continue;
			}

			if ( $node->getAttribute( Attribute::HREF ) === $src ) {
				return true;
			}
		}

		return false;
	}
}
