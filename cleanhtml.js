import fs from 'fs';
import path from 'path';

// Initialize an array to store the footnotes for current page.
let footnotes = [];

// Step 1: Read book.json
const jsonData = fs.readFileSync('book.json', 'utf-8');
let book_data;
try {
    book_data = JSON.parse(jsonData);
} catch (e) {
    console.error('Error: ' + e.message);
    process.exit(1);
}

// Step 2: Get values from the JSON
const src_folder = book_data.src || null;
const dst_folder = book_data.dst || null;
const book_title = book_data.title || null;
const author = book_data.author || null;
const publisher = book_data.publisher || null;

// Step 2a: Check for missing variables and throw error
if (!src_folder) {
    console.error('Error: "src" folder is missing in the JSON.');
    process.exit(1);
} else if (!fs.existsSync(src_folder) || !fs.statSync(src_folder).isDirectory()) {
    console.error('Error: "src" folder does not exist or is not a directory.');
    process.exit(1);
}

if (!dst_folder) {
    console.error('Error: "dst" folder is missing in the JSON.');
    process.exit(1);
}

// Create the destination folder if it doesn't exist.
if (!fs.existsSync(dst_folder) && !fs.mkdirSync(dst_folder, {recursive: true}) && !fs.statSync(dst_folder).isDirectory()) {
    console.error(`Error: Directory "${dst_folder}" was not created`);
    process.exit(1);
}

if (!book_title) {
    console.error('Error: "title" is missing in the JSON.');
    process.exit(1);
}

if (!author) {
    console.error('Error: "author" is missing in the JSON.');
    process.exit(1);
}

if (!publisher) {
    console.error('Error: "publisher" is missing in the JSON.');
    process.exit(1);
}

// Step 3: Read all HTML files in the "src" directory
const html_files = fs.readdirSync(src_folder).filter(file => file.match(/\.(htm|html)$/i));

// Step 4: Loop through each HTML file, change content, and save under the same name
html_files.forEach(file_name => {
    console.log('Processing: ' + file_name);

    // Read the content of the HTML file
    const html_content = fs.readFileSync(path.join(src_folder, file_name), 'utf-8');

    let body_content = extract_book_content(html_content);

    // Clean for unnecessary tags.
    body_content = clean_tags_1(body_content);

    body_content = prepare_footnotes(body_content);

    body_content = clean_tags_2(body_content);

    const file_content = wrap_content(body_content);

    console.log('Saving: ' + file_name);

    let new_file_name = file_name;

    // Check if the original file had a .htm extension, then replace it with .html
    if (path.extname(new_file_name) === '.htm') {
        new_file_name = new_file_name.slice(0, -3) + 'html';

        console.log('Renamed: ' + file_name + ' to ' + new_file_name);

        // Unlink the original file with .htm.
        try {
            fs.unlinkSync(path.join(src_folder, file_name));
            console.log('Deleted: ' + file_name);
        } catch (err) {
            console.error('Error deleting: ' + file_name);
        }
    }

    // Save the content to the file.
    fs.writeFileSync(path.join(src_folder, new_file_name), file_content);
});

// Step 5: Check if "muqadima" file exists and save it under the name "000.html"
const muqadima_file = path.join(src_folder, 'المقدمة.html');
if (fs.existsSync(muqadima_file)) {
    const muqadima_content = fs.readFileSync(muqadima_file, 'utf-8');
    fs.writeFileSync(path.join(src_folder, '000.html'), muqadima_content);
    try {
        fs.unlinkSync(muqadima_file);
        console.log('Renamed "المقدمة" to: 000.html');
    } catch (err) {
        console.error('Error renaming "المقدمة" file. Rename it manually to 000.html');
    }
}

function extract_book_content(html_content) {
    // Extract content within <body> tags
    const bodyRegex = /<body.*?>(.*?)<\/body>/si;
    const bodyMatches = html_content.match(bodyRegex);

    let body_content;
    if (bodyMatches) {
        body_content = bodyMatches[1];
    } else {
        body_content = html_content
    }

    return body_content;
}

function clean_tags_1(body_content) {
    // Remove <div> elements with class="PageHead"
    body_content = body_content.replace(/<div\s+class=['"]PageHead['"][^>]*>.*?<\/div>/gi, '');

    // Remove <span> elements with class="symbol"
    body_content = body_content.replace(/<span\s+class=['"]symbol['"][^>]*>(\s*)<\/span>/gi, '$1');
    body_content = body_content.replace(/<span\s+class=['"]symbol['"][^>]*>(.*?)<\/span>/gi, '$1');

    // Replaces some artificial stuff.
    body_content = body_content.replace(/<font[^>]*>(.{1,2})<\/font>/gi, '$1');

    // Replace <p> and </p> with <br/>
    body_content = body_content.replace(/<p>/gi, '<br/>');
    body_content = body_content.replace(/<\/p>/gi, '<br/>');

    // Replaces deprecated <font> with span.
    body_content = body_content.replace(/<font[^>]*color=['"]?[^'">]+['"]?[^>]*>(.+?)<\/font>/gi, '<span class="highlighted">$1</span>');

    // Remove <hr>.
    body_content = body_content.replace(/<hr[^>]*>/gim, '');

    return body_content;
}

function clean_tags_2(body_content) {
    // Remove all divs.
    body_content = body_content.replace(/<div[^>]*>/gim, '');
    body_content = body_content.replace(/<\/div>/g, ' ');

    // Replaces white spaces.
    body_content = body_content.replace(/&nbsp;/gi, ' ');
    body_content = body_content.replace(/[ ]{1,10}/g, ' ');

    return body_content;
}

function prepare_footnotes(body_content) {
    // Regular expression to parse each page separately.
    return body_content.replace(/<div class=['"]?PageText['"]?[^>]*>(.+)<\/div>/gi, replace_footnotes);
}

function replace_footnotes(all, page_html) {
    let out = page_html;
    // Reset the footnotes array to store the footnotes for current page only.
    footnotes = [];
    out = out.replace(/<div class=['"]?footnote['"]?[^>]*>(.+)<\/div>/im, collect_footnotes);
    out = out.replace(/<sup[^>]*><span[^>]*>\(([\u0660-\u0669]+)\)<\/span><\/sup>/gu, replace_footnotes_in_text);
    // Add <br/> after each page.
    out += '<br/>';
    return out;
}

/**
 * Collects footnotes from the bottom of each page.
 *
 * @param {string} html
 * @return {string}
 */
function collect_footnotes(all, footnote_html) {
    let matches;
    // Find all matches
    const regex = /<span class="highlighted">\(([\u0660-\u0669]+)\)<\/span>(.*?)(?:<br\/>|<\/div|$)/igum;
    while ((matches = regex.exec(all)) !== null) {
        // Convert Arabic numbers to Latin numbers.
        const fn_number = arab2latin(matches[1]);

        // Set the footnote text.
        let fn_text = matches[2];
        // Remove leading and trailing spaces and dots.
        fn_text = fn_text.replace(/^[\s\.]+|[\s\.]+$/gm, '');
        // Add footnote number to the object.
        footnotes[fn_number] = fn_text;
    }

    // Return empty string to remove the footnote div from the page.
    return '';
}

/**
 * Replaces footnote references in the text with the actual footnotes.
 * Actual footnotes collected from the bottom of each page.
 *
 * @param {string} html
 * @return {string}
 */
function replace_footnotes_in_text(all, number) {
    // Convert Arabic numbers to Latin numbers.
    const fn_num = arab2latin(number);

    // If the footnote exists, return it.
    if (footnotes[fn_num]) {
        return ` <span class="footnote-inline">(${footnotes[fn_num]})</span> `;
    }

    // Return as it was.
    return all;
}

/**
 * Converts Arabic numbers to Latin numbers.
 *
 * @param {string} input
 * @return {string}
 */
function arab2latin(input) {
    const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    const latinNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    // Replace Arabic numbers with Latin numbers.
    return input.split('').map(char => {
        const index = arabicNumbers.indexOf(char);
        return index !== -1 ? latinNumbers[index] : char;
    }).join('');
}

function wrap_content(body_content) {
    return `<!DOCTYPE html><html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" type="text/css" href="../css/styles.css">
</head><body>\n${body_content}\n</body></html>`;
}
