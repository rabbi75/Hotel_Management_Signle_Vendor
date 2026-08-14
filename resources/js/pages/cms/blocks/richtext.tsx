import { Section } from '../section';
import { str, type BlockRendererProps } from './support';

/**
 * Rendered as HTML.
 *
 * Safe because the value is never trusted as it arrives: BlockSchema::sanitise()
 * passes every `richtext` field through the blog module's HtmlSanitizer — an
 * allow list of elements, attributes and URL schemes — and does so on read as
 * well as on write, so rows stored before that was true are cleaned too.
 * Nothing the author's browser produces reaches this component unfiltered.
 */
export default function RichtextBlock({ data }: BlockRendererProps) {
    const content = str(data, 'content');

    if (content === '') {
        return null;
    }

    return (
        <Section data={data}>
            <div className="prose-content" dangerouslySetInnerHTML={{ __html: content }} />
        </Section>
    );
}
