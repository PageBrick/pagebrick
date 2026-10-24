// PageBrick panel: everything a person writes travels in ONE field, "pb_form" (pb_unseal_post() in core/helpers.php puts it back). The fields are written as a query
// string and then in base64. The firewall of many hosts (Mod_Security) reads every field of a form and answers "Not Acceptable" to what looks like an attack, and a link to
// another site, "<script" or a few words of a database command are everyday text for whoever writes a site: the Settings form was refused for a LinkedIn address.
// The CSRF token and the files stay outside. Only the screens of a signed-in person do it (they carry the meta tag). This file loads before the others: the page editor
// sends its first preview the moment it starts. admin.js seals the forms that are submitted (after it has written the code boxes), studio.js its own requests.
(() => {
    const signedIn = !!document.querySelector('meta[name=pb-csrf]');
    window.pbSeal = (data) => {
        if (!signedIn) return data;
        const text = new URLSearchParams();
        const out = new FormData();
        for (const [name, value] of data) {
            if (value instanceof File || name === '_csrf') out.append(name, value);
            else text.append(name, value);
        }
        const bytes = new TextEncoder().encode(text.toString());
        let binary = '';
        for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
        out.append('pb_form', btoa(binary).replace(/=+$/, '')); // no "=" padding: a firewall reads "==" as an SQL operator
        return out;
    };
})();
