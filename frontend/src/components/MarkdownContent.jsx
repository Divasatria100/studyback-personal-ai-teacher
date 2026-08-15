import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import remarkMath from 'remark-math';
import rehypeKatex from 'rehype-katex';
import 'katex/dist/katex.min.css';
import { cn } from './Shared';

// Renders trusted-but-external AI content as safe Markdown.
// - react-markdown builds a virtual DOM (no dangerouslySetInnerHTML),
//   so raw HTML from the AI response is never interpreted.
// - remark-gfm adds tables, strikethrough, task lists, autolinks.
// - remark-math + rehype-katex render $...$ / $$...$$ as LaTeX math.
export default function MarkdownContent({ children, className }) {
  return (
    <div className={cn('ai-markdown', className)}>
      <ReactMarkdown
        remarkPlugins={[remarkMath, remarkGfm]}
        rehypePlugins={[rehypeKatex]}
        components={{
          // Wrap every GFM table so it can scroll horizontally inside the
          // bubble instead of pushing the whole page wide on small screens.
          table: ({ node, ...props }) => (
            <div className="ai-markdown-table-wrap">
              <table {...props} />
            </div>
          ),
          // Open external links in a new tab, safely.
          a: ({ node, ...props }) => {
            const { href } = props;
            const isExternal = !!href && /^(https?:)?\/\//.test(href);
            if (isExternal) {
              return <a {...props} target="_blank" rel="noopener noreferrer" />;
            }
            return <a {...props} />;
          },
        }}
      >
        {children}
      </ReactMarkdown>
    </div>
  );
}