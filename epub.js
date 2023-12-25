import {EPub} from "@lesjoursfr/html-to-epub";
import fs from 'fs';
import path from 'path';
import book from './bookData.js';

// Get the book data
await book.getData();

// Check the book data
book.checkData();

// Path to the CSS file.
const pathToCSSFile = './css/styles.css';
// Read the content of the CSS file synchronously.
const cssContent = fs.readFileSync(pathToCSSFile, 'utf-8');

// Create options object for the EPub constructor.
let epubOptions = {
    title: book.info.title,
    author: book.info.author,
    publisher: book.info.publisher,
    lang: "ar",
    tocTitle: "جدول المحتويات",
    customOpfTemplatePath: "./content.opf.ejs",
    css: cssContent,
    fonts: ['./fonts/Kitab-Regular.ttf', './fonts/Kitab-Bold.ttf'],
    content: [],
    output: book.info.dst + '/' + book.info.title + '.epub'
};

// Step 3: Read all HTML files in the "src" directory
const html_files = fs.readdirSync(book.info.src).filter(file => file.match(/\.(html)$/i));

// Step 4: Loop through each HTML file, change content, and save under the same name
html_files.forEach(file_name => {
    console.log('Processing: ' + file_name);

    let part_name = path.parse(file_name).name;
    // If only numbers in the file name, then add "Part-" to the beginning of the file name.
    const match = part_name.match(/^(\d+)$/);
    if (match) {
        const part_number = parseInt(match[1]);
        if (part_number === 0) {
            part_name = `مقدمة`;
        } else {
            part_name = `جزء-${part_number}`;
        }
    }

    epubOptions.content.push({
        title: part_name,
        data: fs.readFileSync(path.join(book.info.src, file_name), 'utf-8')
    });
});

/**
 cover: Book cover image (optional), File path (absolute path) or web url, eg. "http://abc.com/book-cover.jpg" or "/User/Alice/images/book-cover.jpg"
 output Out put path (absolute path), you can also path output as the second argument when use new , eg: new Epub(options, output)
 appendChapterTitles: Automatically append the chapter title at the beginning of each contents. You can disable that by specifying false.
 customNcxTocTemplatePath: Optional. For advanced customizations: absolute path to a NCX toc template.
 customHtmlTocTemplatePath: Optional. For advanced customizations: absolute path to a HTML toc template.
 excludeFromToc: optional, if is not shown on Table of content, default: false;
 beforeToc: optional, if is shown before Table of content, such like copyright pages. default: false;
 filename: optional, specify filename for each chapter, default: undefined;
 verbose: specify whether or not to console.log progress messages, default: false.
 */

const epub = new EPub(epubOptions, epubOptions.output);
epub.render()
    .then(() => {
        console.log("Ebook Generated Successfully!");
    })
    .catch((err) => {
        console.error("Failed to generate Ebook because of ", err);
    });