async function svgToPng(svgElement) {
    const { width, height } = svgDimensions(svgElement);
    const svgClone = svgElement.cloneNode(true);

    inlineComputedSvgStyles(svgElement, svgClone);
    svgClone.setAttribute("xmlns", "http://www.w3.org/2000/svg");
    svgClone.setAttribute("xmlns:xlink", "http://www.w3.org/1999/xlink");
    svgClone.setAttribute("width", width);
    svgClone.setAttribute("height", height);

    // 1. Get SVG data
    const svgData = new XMLSerializer().serializeToString(svgClone);
    const svgBlob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
    const url = URL.createObjectURL(svgBlob);

    // 2. Load SVG into image (wrapped in promise)
    const img = await new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = url;
    });

    // 3. Create canvas and draw
    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext("2d");
    ctx.drawImage(img, 0, 0, width, height);

    URL.revokeObjectURL(url);

    // 4. Create PNG image element
    const pngImage = new Image();
    pngImage.src = canvas.toDataURL("image/png");

    return pngImage;
}

function svgDimensions(svgElement) {
    const renderedSize = svgElement.getBoundingClientRect();
    const viewBox = svgElement.viewBox.baseVal;
    const attributeSize = {
        width: svgElement.width.baseVal.value,
        height: svgElement.height.baseVal.value,
    };
    const viewBoxSize = {
        width: viewBox ? viewBox.width : 0,
        height: viewBox ? viewBox.height : 0,
    };

    return {
        width: firstPositiveSize(renderedSize.width, attributeSize.width, viewBoxSize.width, 300),
        height: firstPositiveSize(renderedSize.height, attributeSize.height, viewBoxSize.height, 150),
    };
}

function firstPositiveSize(...sizes) {
    const size = sizes.find((size) => size > 0);

    return Math.ceil(size);
}

function inlineComputedSvgStyles(sourceSvg, targetSvg) {
    const sourceElements = [sourceSvg, ...sourceSvg.querySelectorAll("*")];
    const targetElements = [targetSvg, ...targetSvg.querySelectorAll("*")];
    const properties = [
        "clip-rule",
        "color",
        "display",
        "dominant-baseline",
        "fill",
        "fill-opacity",
        "fill-rule",
        "font-family",
        "font-size",
        "font-style",
        "font-weight",
        "opacity",
        "stroke",
        "stroke-dasharray",
        "stroke-dashoffset",
        "stroke-linecap",
        "stroke-linejoin",
        "stroke-miterlimit",
        "stroke-opacity",
        "stroke-width",
        "text-anchor",
        "vector-effect",
        "visibility",
    ];

    sourceElements.forEach((sourceElement, index) => {
        const targetElement = targetElements[index];
        const computedStyle = getComputedStyle(sourceElement);

        properties.forEach((property) => {
            const value = computedStyle.getPropertyValue(property);

            if (value) {
                targetElement.setAttribute(property, value);
            }
        });
    });
}

module.exports = { svgToPng };
