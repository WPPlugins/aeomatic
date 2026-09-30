<?php
/**
 * Answer-first: find answers buried under question headings and move them up.
 *
 * AI assistants read the visible text of a page and tend to quote the first
 * clear sentence after a question. When the heading asks "What is AEO?" and
 * the definition only arrives in the fourth sentence, the section opens with
 * filler instead of the answer.
 *
 * find()  — rules-based detection on the extracted HTML (any editor, including
 *           page builders). For each question heading, and for the post title
 *           when it is a question, it scores every sentence in the section for
 *           how directly it answers that question type and flags sections where
 *           a clearly better answer sits below a weak opening.
 * apply() — moves each such answer sentence to the top of its section in
 *           post_content. Block editor and Classic editor only; page-builder
 *           layouts are stored in builder-specific data and are left alone
 *           (the finding still shows the suggestion). The edit goes through
 *           wp_update_post(), so WordPress keeps a revision to undo it.
 *
 * Everything is local. No AI service is called.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Answer_First {

	/** Minimum score for a sentence to count as a real answer. */
	const MIN_ANSWER_SCORE = 2.5;

	/** How much better the buried answer must score than the opening sentence. */
	const MIN_MARGIN = 1.0;

	/** Only look this many sentences into a section. */
	const MAX_SENTENCES = 12;

	const QUESTION_WORDS = 'what|how|why|when|where|who|whom|which|can|could|does|do|did|is|are|was|were|should|will|would|may';

	const STOPWORDS = array(
		'what', 'how', 'why', 'when', 'where', 'who', 'whom', 'which', 'can', 'could', 'does', 'do', 'did', 'is', 'are', 'was', 'were',
		'should', 'will', 'would', 'may', 'the', 'a', 'an', 'of', 'to', 'in', 'on', 'for', 'and', 'or', 'with', 'your', 'you', 'my',
		'i', 'we', 'our', 'it', 'its', 'this', 'that', 'these', 'those', 'be', 'by', 'from', 'at', 'as', 'about', 'into', 'than',
		'much', 'many', 'long', 'best', 'there', 'get', 'use', 'need', 'take', 'make', 'work', 'really', 'actually', 'exactly',
	);

	// ── Detection ─────────────────────────────────────────────────────────

	/**
	 * Buried answers on a post.
	 *
	 * @return array<int,array{question:string,answer:string,answer_clean:string,position:int,opening:string,is_title:bool}>
	 */
	public static function find( WP_Post $post ): array {
		$extract = AEOMATIC_Content_Extractor::extract( $post );
		$html    = (string) ( $extract['html'] ?? '' );
		if ( '' === trim( $html ) ) {
			return array();
		}

		$sections = self::sections( $html, get_the_title( $post ) );
		$out      = array();

		foreach ( $sections as $section ) {
			$finding = self::analyse_section( $section['question'], $section['text'] );
			if ( $finding ) {
				$finding['is_title'] = $section['is_title'];
				$out[]               = $finding;
			}
		}

		/**
		 * Filter answer-first findings for a post.
		 *
		 * @param array   $out  Findings.
		 * @param WP_Post $post The post.
		 */
		return (array) apply_filters( 'aeomatic_answer_first_findings', $out, $post );
	}

	/**
	 * Split HTML into question sections: each question heading with the text
	 * that follows it until the next heading of the same or higher level, plus
	 * the intro (text before the first heading) when the title is a question.
	 *
	 * @return array<int,array{question:string,text:string,is_title:bool}>
	 */
	public static function sections( string $html, string $title = '' ): array {
		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div id="aeomatic-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$xp   = new DOMXPath( $doc );
		$root = $xp->query( '//*[@id="aeomatic-root"]' )->item( 0 );
		if ( ! $root ) {
			return array();
		}

		// Flatten into a stream of (heading|text) chunks in document order.
		$stream = array();
		self::walk( $root, $stream );

		$sections = array();
		$current  = null;
		$intro    = '';
		$seen_h   = false;

		foreach ( $stream as $chunk ) {
			if ( 'h' === $chunk['type'] ) {
				if ( $current ) {
					$sections[] = $current;
				}
				$seen_h  = true;
				$current = self::is_question( $chunk['text'] )
					? array( 'question' => $chunk['text'], 'text' => '', 'level' => $chunk['level'], 'is_title' => false )
					: null;
				continue;
			}
			if ( null !== $current ) {
				$current['text'] .= ' ' . $chunk['text'];
			} elseif ( ! $seen_h ) {
				$intro .= ' ' . $chunk['text'];
			}
		}
		if ( $current ) {
			$sections[] = $current;
		}

		$title = self::clean( $title );
		if ( '' !== $title && self::is_question( $title ) && '' !== trim( $intro ) ) {
			array_unshift( $sections, array( 'question' => $title, 'text' => $intro, 'level' => 1, 'is_title' => true ) );
		}

		foreach ( $sections as &$s ) {
			$s['text'] = self::clean( $s['text'] );
			unset( $s['level'] );
		}
		unset( $s );

		return array_values( array_filter( $sections, static function ( $s ) {
			return '' !== $s['text'];
		} ) );
	}

	/**
	 * Depth-first walk collecting headings and block-level text.
	 *
	 * @param array $stream Output, by reference.
	 */
	private static function walk( DOMNode $node, array &$stream ): void {
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				if ( XML_TEXT_NODE === $child->nodeType && '' !== trim( $child->nodeValue ) ) {
					$stream[] = array( 'type' => 't', 'text' => self::clean( $child->nodeValue ) );
				}
				continue;
			}
			$tag = strtolower( $child->nodeName );
			if ( in_array( $tag, array( 'script', 'style', 'template', 'svg', 'figure', 'nav' ), true ) ) {
				continue;
			}
			if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
				$stream[] = array( 'type' => 'h', 'level' => (int) $m[1], 'text' => self::clean( $child->textContent ) );
				continue;
			}
			if ( in_array( $tag, array( 'p', 'li', 'blockquote', 'dd', 'td' ), true ) ) {
				$stream[] = array( 'type' => 't', 'text' => self::clean( $child->textContent ) );
				continue;
			}
			self::walk( $child, $stream );
		}
	}

	/** Is this heading text phrased as a question? */
	public static function is_question( string $text ): bool {
		$text = trim( $text );
		if ( '' === $text || str_word_count( $text ) > 18 ) {
			return false;
		}
		if ( '?' === substr( $text, -1 ) ) {
			return true;
		}
		return (bool) preg_match( '/^(' . self::QUESTION_WORDS . ')\b/i', $text ) && str_word_count( $text ) >= 3;
	}

	/**
	 * Score sentences in one section and report a buried answer, if any.
	 *
	 * @return array{question:string,answer:string,answer_clean:string,position:int,opening:string}|null
	 */
	public static function analyse_section( string $question, string $text ): ?array {
		$sentences = array_slice( self::sentences( $text ), 0, self::MAX_SENTENCES );
		if ( count( $sentences ) < 2 ) {
			return null;
		}

		$kind     = self::question_kind( $question );
		$keywords = self::keywords( $question );
		$scores   = array();
		foreach ( $sentences as $i => $s ) {
			$scores[ $i ] = self::score( $s, $kind, $keywords );
		}

		$best = 0;
		foreach ( $scores as $i => $sc ) {
			if ( $sc > $scores[ $best ] ) {
				$best = $i;
			}
		}

		if ( 0 === $best || $scores[ $best ] < self::MIN_ANSWER_SCORE || ( $scores[ $best ] - $scores[0] ) < self::MIN_MARGIN ) {
			return null;
		}

		return array(
			'question'     => $question,
			'answer'       => $sentences[ $best ],
			'answer_clean' => self::strip_filler( $sentences[ $best ] ),
			'position'     => $best + 1,
			'opening'      => $sentences[0],
		);
	}

	/** Broad question type, which decides what a good answer contains. */
	private static function question_kind( string $q ): string {
		$q = strtolower( $q );
		if ( preg_match( '/^(how (much|many|long|often|far|big|old|soon)|when|what year|what time|how fast)\b/', $q ) ) {
			return 'number';
		}
		if ( preg_match( '/^why\b/', $q ) ) {
			return 'reason';
		}
		if ( preg_match( '/^(what|who)\s+(is|are|was|were|does .* mean)\b/', $q ) || preg_match( '/\bmean\??$/', $q ) ) {
			return 'definition';
		}
		if ( preg_match( '/^(can|could|does|do|did|is|are|was|were|should|will|would|may)\b/', $q ) ) {
			return 'yesno';
		}
		if ( preg_match( '/^how\b/', $q ) ) {
			return 'method';
		}
		return 'general';
	}

	/**
	 * Topic words of a question: lowercase words minus question words and
	 * stopwords. Acronyms keep their case so they can be matched to expansions.
	 *
	 * @return array<int,string>
	 */
	private static function keywords( string $q ): array {
		preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'-]*/u', $q, $m );
		$out = array();
		foreach ( $m[0] as $w ) {
			$lw = mb_strtolower( $w );
			if ( in_array( $lw, self::STOPWORDS, true ) || mb_strlen( $w ) < 2 ) {
				continue;
			}
			$out[] = preg_match( '/^[A-Z]{2,6}s?$/', $w ) ? rtrim( $w, 's' ) : $lw;
		}
		return array_values( array_unique( $out ) );
	}

	/** How well a sentence answers a question of this kind, about these words. */
	private static function score( string $sentence, string $kind, array $keywords ): float {
		$lower = mb_strtolower( $sentence );
		$score = 0.0;

		// Topic overlap (acronyms match their expansion: AEO ~ answer engine optimization).
		if ( $keywords ) {
			$hit = 0;
			foreach ( $keywords as $k ) {
				if ( preg_match( '/^[A-Z]{2,6}$/', $k ) ) {
					if ( false !== strpos( $sentence, $k ) || self::has_expansion( $lower, $k ) ) {
						++$hit;
					}
				} elseif ( preg_match( '/\b' . preg_quote( $k, '/' ) . '/u', $lower ) ) {
					++$hit;
				}
			}
			$score += 2.0 * ( $hit / count( $keywords ) );
		}

		switch ( $kind ) {
			case 'definition':
				if ( preg_match( '/\b(is|are|means|refers to|stands for|describes|is defined as)\b/', $lower ) ) {
					$score += 1.5;
				}
				break;
			case 'number':
				if ( preg_match( '/\d|\b(one|two|three|four|five|six|seven|eight|nine|ten|twelve|dozen|hundred|thousand|minutes?|hours?|days?|weeks?|months?|years?)\b/', $lower ) ) {
					$score += 1.5;
				}
				break;
			case 'reason':
				if ( preg_match( '/\b(because|since|due to|so that|which means|as a result|the reason)\b/', $lower ) ) {
					$score += 1.5;
				}
				break;
			case 'yesno':
				if ( preg_match( '/^(yes|no)\b|\b(you can|you cannot|you can\'t|it does|it doesn\'t|it is|it isn\'t)\b/', $lower ) ) {
					$score += 1.5;
				}
				break;
			case 'method':
				if ( preg_match( '/\b(by|use|using|start|first|then|step|go to|open|click|add|set)\b/', $lower ) ) {
					$score += 1.0;
				}
				break;
		}

		// Concise sentences are easier to quote.
		$words = str_word_count( $sentence );
		if ( $words >= 6 && $words <= 35 ) {
			$score += 0.5;
		}

		// Filler and hedging openers are not answers.
		if ( preg_match( '/^(there are many|many people|some (say|people)|it depends|in this (article|post|guide)|let\'s|before we|have you ever|if you\'ve ever|you may be wondering|great question|others disagree)/', $lower ) ) {
			$score -= 1.5;
		}

		return $score;
	}

	/** Does lowercase $text contain words whose initials spell $acronym? */
	private static function has_expansion( string $text, string $acronym ): bool {
		$letters = str_split( strtolower( $acronym ) );
		$pattern = '/\b' . implode( '[\p{L}\'-]*\s+(?:(?:and|of|for|the|&)\s+)?', array_map( static function ( $l ) {
			return preg_quote( $l, '/' );
		}, $letters ) ) . '[\p{L}\'-]*/u';
		return (bool) preg_match( $pattern, $text );
	}

	/**
	 * Remove a leading filler phrase so the moved sentence reads as an opener.
	 * "In the end, answer engine optimization is…" → "Answer engine optimization is…"
	 */
	public static function strip_filler( string $s ): string {
		$stripped = preg_replace( '/^(in the end|ultimately|basically|simply put|in short|in a nutshell|so|well|put simply|to put it simply|in other words|essentially|long story short|the short answer is|the answer is)\s*[,:]?\s+/i', '', $s );
		$stripped = (string) $stripped;
		if ( '' === $stripped || $stripped === $s ) {
			return $s;
		}
		return mb_strtoupper( mb_substr( $stripped, 0, 1 ) ) . mb_substr( $stripped, 1 );
	}

	/**
	 * @return array<int,string>
	 */
	private static function sentences( string $text ): array {
		$parts = preg_split( '/(?<=[.!?])\s*(?=[\p{Lu}\p{N}"\x{201C}])/u', self::clean( $text ) );
		$out   = array();
		foreach ( (array) $parts as $p ) {
			$p = trim( (string) $p );
			if ( '' !== $p && str_word_count( $p ) >= 3 ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	private static function clean( string $s ): string {
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( (string) $s, " \t\n\r\0\x0B" );
	}

	// ── Fixing ────────────────────────────────────────────────────────────

	/** Can apply() edit this post's content safely? */
	public static function can_apply( WP_Post $post ): bool {
		$builder = AEOMATIC_Content_Extractor::detect_builder( $post );
		return in_array( $builder, array( 'gutenberg', 'classic' ), true );
	}

	/**
	 * Move every buried answer that can be moved safely to the top of its
	 * section, and save the post (WordPress keeps a revision).
	 *
	 * @return array{moved:int,skipped:int,content:string}
	 */
	public static function apply( WP_Post $post ): array {
		$findings = self::find( $post );
		$content  = (string) $post->post_content;
		$moved    = 0;
		$skipped  = 0;

		if ( ! $findings || ! self::can_apply( $post ) ) {
			return array( 'moved' => 0, 'skipped' => count( $findings ), 'content' => $content );
		}

		foreach ( $findings as $f ) {
			$new = has_blocks( $content )
				? self::move_in_blocks( $content, $f )
				: self::move_in_html( $content, $f );
			if ( null === $new ) {
				++$skipped;
				continue;
			}
			$content = $new;
			++$moved;
		}

		if ( $moved > 0 ) {
			wp_update_post( wp_slash( array(
				'ID'           => $post->ID,
				'post_content' => $content,
			) ) );
			AEOMATIC_Content_Extractor::forget( $post->ID );
			AEOMATIC_Activity::log(
				'answer_first_fix',
				sprintf(
					/* translators: 1: number of answers moved, 2: post title */
					_n( 'Moved %1$d answer to the top of its section in "%2$s".', 'Moved %1$d answers to the top of their sections in "%2$s".', $moved, 'aeomatic' ),
					$moved,
					get_the_title( $post )
				)
			);
		}

		return array( 'moved' => $moved, 'skipped' => $skipped, 'content' => $content );
	}

	/**
	 * Block editor content: remove the answer sentence from its paragraph block
	 * and insert it as a new paragraph block directly under the question.
	 */
	private static function move_in_blocks( string $content, array $f ): ?string {
		$blocks = parse_blocks( $content );
		$done   = false;
		$blocks = self::move_in_block_list( $blocks, $f, $done );
		return $done ? serialize_blocks( $blocks ) : null;
	}

	/**
	 * @param array $blocks Block list.
	 * @param array $f      Finding.
	 * @param bool  $done   Set to true once moved.
	 */
	private static function move_in_block_list( array $blocks, array $f, bool &$done ): array {
		$count = count( $blocks );

		// Where does the section start? After the matching heading, or at the
		// top of the post for a question title.
		$starts = array();
		if ( ! empty( $f['is_title'] ) ) {
			$starts[] = 0;
		}
		for ( $i = 0; $i < $count; $i++ ) {
			if ( 'core/heading' === $blocks[ $i ]['blockName'] && self::clean( wp_strip_all_tags( (string) $blocks[ $i ]['innerHTML'] ) ) === $f['question'] ) {
				$starts[] = $i + 1;
			}
		}

		foreach ( $starts as $start ) {
			$first_para = null;
			for ( $j = $start; $j < $count; $j++ ) {
				$name = (string) $blocks[ $j ]['blockName'];
				if ( 'core/heading' === $name ) {
					break;
				}
				if ( 'core/paragraph' !== $name ) {
					continue;
				}
				if ( null === $first_para ) {
					$first_para = $j;
				}
				$html = (string) $blocks[ $j ]['innerHTML'];
				$pos  = self::find_sentence( $html, $f['answer'] );
				if ( null === $pos ) {
					continue;
				}

				// Take the sentence (and the whitespace after it) out of the paragraph.
				$new_html = self::remove_at( $html, $pos, $f['answer'] );
				$blocks[ $j ]['innerHTML']    = $new_html;
				$blocks[ $j ]['innerContent'] = array( $new_html );
				$emptied                      = '' === trim( wp_strip_all_tags( $new_html ) );

				$new_block = array(
					'blockName'    => 'core/paragraph',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '<p>' . esc_html( $f['answer_clean'] ) . '</p>',
					'innerContent' => array( '<p>' . esc_html( $f['answer_clean'] ) . '</p>' ),
				);

				if ( $emptied ) {
					array_splice( $blocks, $j, 1 );
				}
				array_splice( $blocks, (int) $first_para, 0, array( $new_block ) );
				$done = true;
				return $blocks;
			}
		}

		// Headings nested in groups, columns etc.
		foreach ( $blocks as $k => $b ) {
			if ( ! empty( $b['innerBlocks'] ) && ! $done ) {
				$blocks[ $k ]['innerBlocks'] = self::move_in_block_list( $b['innerBlocks'], $f, $done );
				if ( $done ) {
					// innerContent holds null placeholders for each inner block;
					// the count only changes if a paragraph was removed or added.
					$blocks[ $k ]['innerContent'] = self::rebuild_inner_content( $b, count( $blocks[ $k ]['innerBlocks'] ) );
				}
			}
		}
		return $blocks;
	}

	/**
	 * Keep a parent block's innerContent in step with its innerBlocks count.
	 * Inner blocks are marked by null entries; extra ones go after the last
	 * existing placeholder, removed ones are dropped from the end.
	 */
	private static function rebuild_inner_content( array $block, int $inner_count ): array {
		$content = (array) $block['innerContent'];
		$nulls   = array_keys( array_filter( $content, 'is_null' ) );
		$diff    = $inner_count - count( $nulls );
		if ( $diff > 0 ) {
			$at = $nulls ? end( $nulls ) + 1 : count( $content );
			array_splice( $content, $at, 0, array_fill( 0, $diff, null ) );
		} elseif ( $diff < 0 ) {
			for ( $n = 0; $n < -$diff; $n++ ) {
				$last = array_pop( $nulls );
				array_splice( $content, $last, 1 );
			}
		}
		return $content;
	}

	/**
	 * Classic editor content: remove the sentence from the section and insert
	 * it as its own paragraph right after the question heading.
	 */
	private static function move_in_html( string $content, array $f ): ?string {
		if ( ! empty( $f['is_title'] ) ) {
			$section_start = 0;
		} else {
			if ( ! preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', $content, $m, PREG_OFFSET_CAPTURE ) ) {
				return null;
			}
			$section_start = null;
			foreach ( $m[0] as $idx => $whole ) {
				if ( self::clean( wp_strip_all_tags( $m[2][ $idx ][0] ) ) === $f['question'] ) {
					$section_start = $whole[1] + strlen( $whole[0] );
					break;
				}
			}
			if ( null === $section_start ) {
				return null;
			}
		}

		$rest    = substr( $content, $section_start );
		$next_h  = preg_match( '#<h[1-6][^>]*>#i', $rest, $n, PREG_OFFSET_CAPTURE ) ? $n[0][1] : strlen( $rest );
		$section = substr( $rest, 0, $next_h );
		$pos     = self::find_sentence( $section, $f['answer'] );
		if ( null === $pos ) {
			return null;
		}

		$section = self::remove_at( $section, $pos, $f['answer'] );
		$section = (string) preg_replace( '/[ \t]+(\r?\n)/', '$1', $section );
		$section = "\n\n" . esc_html( $f['answer_clean'] ) . "\n\n" . ltrim( $section );

		return substr( $content, 0, $section_start ) . $section . substr( $rest, $next_h );
	}

	/**
	 * Byte offset of $sentence inside $html, only when it appears verbatim
	 * (no markup inside it). Tries the raw and HTML-escaped spellings.
	 */
	private static function find_sentence( string $html, string $sentence ): ?int {
		foreach ( array( $sentence, esc_html( $sentence ), str_replace( "'", '&#8217;', $sentence ) ) as $needle ) {
			$pos = strpos( $html, $needle );
			if ( false !== $pos ) {
				return $pos;
			}
		}
		return null;
	}

	/** Remove the sentence at $pos plus the whitespace that follows it. */
	private static function remove_at( string $html, int $pos, string $sentence ): string {
		$len = strlen( $sentence );
		foreach ( array( esc_html( $sentence ), str_replace( "'", '&#8217;', $sentence ) ) as $alt ) {
			if ( substr( $html, $pos, strlen( $alt ) ) === $alt ) {
				$len = max( $len, strlen( $alt ) );
			}
		}
		$after = substr( $html, $pos + $len );
		$after = ltrim( $after, " \t" );
		$out   = substr( $html, 0, $pos ) . $after;
		// Tidy a space left before a closing tag: "text. </p>" → "text.</p>".
		return (string) preg_replace( '/\s+(<\/(p|li|div)>)/', '$1', $out );
	}
}
