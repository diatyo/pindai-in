using System;
using System.Diagnostics;
using System.IO;
using System.Windows.Forms;
using System.Collections.Generic;
using System.Web.Script.Serialization;

class Launcher {
    [STAThread]
    static void Main(string[] args) {
        try {
            string root = AppDomain.CurrentDomain.BaseDirectory;
            string powershell = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.System), "WindowsPowerShell\\v1.0\\powershell.exe");
            if (args.Length > 0 && args[0] == "--configure") {
                var info = new ProcessStartInfo(powershell, "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"" + Path.Combine(root, "Install-Service.ps1") + "\" -Action Install");
                info.UseShellExecute = true; info.Verb = "runas"; info.WindowStyle = ProcessWindowStyle.Hidden;
                using(var process=Process.Start(info)) {
                    process.WaitForExit();
                    if(process.ExitCode!=0) throw new Exception("Penyiapan service belum selesai. Jalankan Siapkan Pindai.in dari menu Start dengan izin administrator.");
                }
                return;
            }
            string connection = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),"PindaiIn","connection.json");
            if(!File.Exists(connection)) throw new Exception("Jalankan Siapkan Pindai.in dari menu Start terlebih dahulu.");
            var config=new JavaScriptSerializer().Deserialize<Dictionary<string,string>>(File.ReadAllText(connection));
            Uri url;
            if(!Uri.TryCreate(config["url"],UriKind.Absolute,out url) || url.Scheme!="http" || url.Host!="127.0.0.1") throw new Exception("Konfigurasi alamat lokal tidak valid.");
            Process.Start(new ProcessStartInfo(url.AbsoluteUri){UseShellExecute=true});
        } catch (Exception error) {
            MessageBox.Show("Pindai.in belum dapat dibuka.\n" + error.Message, "Pindai.in", MessageBoxButtons.OK, MessageBoxIcon.Error);
            Environment.Exit(1);
        }
    }
}
